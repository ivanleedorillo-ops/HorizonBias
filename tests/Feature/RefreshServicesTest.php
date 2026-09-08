<?php

namespace Tests\Feature;

use App\Contracts\MarketDataProvider;
use App\Models\BiasSnapshot;
use App\Models\MacroBrief;
use App\Services\Analysis\BiasScorer;
use App\Services\Analysis\IndicatorCalculator;
use App\Services\Macro\MacroRefreshService;
use App\Services\Market\MarketMode;
use App\Services\Market\MarketRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\MakesCandles;
use Tests\TestCase;

class RefreshServicesTest extends TestCase
{
    use MakesCandles, RefreshDatabase;

    #[Test]
    public function market_refresh_persists_candles_and_a_snapshot_idempotently(): void
    {
        config(['horizon.market_mode' => 'live']);
        $provider = new class($this->candles()) implements MarketDataProvider
        {
            public function __construct(private array $candles) {}

            public function fetch(string $timeframe): array
            {
                return $this->candles;
            }

            public function name(): string
            {
                return 'Fake';
            }
        };
        $service = new MarketRefreshService($provider, new BiasScorer(new IndicatorCalculator), new MarketMode);
        $service->refresh('1h');
        $service->refresh('1h');
        $this->assertDatabaseCount('market_candles', 250);
        $this->assertDatabaseCount('bias_snapshots', 2);
        $this->assertDatabaseHas('bias_snapshots', ['timeframe' => '1h', 'score' => 65, 'status' => 'ready']);
    }

    #[Test]
    public function a_market_failure_marks_the_last_good_snapshot_stale(): void
    {
        config(['horizon.market_mode' => 'live']);
        BiasSnapshot::create(['symbol' => 'XAU/USD', 'timeframe' => '1h', 'score' => 20, 'label' => 'Bullish', 'component_scores' => [], 'metrics' => [], 'explanations' => [], 'provider' => 'Fake', 'data_as_of' => now(), 'generated_at' => now(), 'status' => 'ready']);
        $provider = new class implements MarketDataProvider
        {
            public function fetch(string $timeframe): array
            {
                throw new RuntimeException('offline');
            }

            public function name(): string
            {
                return 'Fake';
            }
        };
        try {
            (new MarketRefreshService($provider, new BiasScorer(new IndicatorCalculator), new MarketMode))->refresh('1h');
        } catch (RuntimeException) {
        }
        $this->assertDatabaseHas('bias_snapshots', ['timeframe' => '1h', 'status' => 'stale']);
    }

    #[Test]
    public function macro_refresh_persists_sources_and_marks_fallback_stale(): void
    {
        config([
            'horizon.ai.dual_enabled' => true,
            'horizon.ai.free_tier_only' => true,
            'horizon.ai.daily_request_cap' => 24,
            'horizon.macro_feeds.sources' => [],
            'horizon.gemini.api_key' => 'gemini-test',
            'horizon.gemini.model' => 'gemini-3.5-flash-lite',
            'horizon.gemini.free_tier_models' => ['gemini-3.5-flash-lite'],
            'horizon.groq.api_key' => 'groq-test',
            'horizon.groq.model' => 'openai/gpt-oss-120b',
            'horizon.groq.base_url' => 'https://api.groq.test/openai/v1',
            'horizon.groq.free_tier_models' => ['openai/gpt-oss-120b'],
        ]);
        $valid = [
            'gold_bias' => 'neutral', 'usd_strength' => 'neutral', 'risk_level' => 'medium',
            'confidence' => 60, 'summary' => 'Mixed forces.',
            'supporting_factors' => [], 'opposing_factors' => [], 'risk_factors' => [], 'citations' => [],
        ];
        Http::fake([
            '*/interactions' => Http::sequence()
                ->push(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($valid)]]]]])
                ->push([], 429),
            'api.groq.test/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode($valid)]]]])
                ->push([], 429),
        ]);

        app(MacroRefreshService::class)->refresh();

        $this->assertDatabaseHas('macro_briefs', [
            'stance' => 'mixed', 'agreement' => 'agree', 'status' => 'ready', 'prompt_version' => 'dual-ai-v1',
        ]);
        $this->assertCount(2, MacroBrief::latest('generated_at')->first()->analyses);

        try {
            app(MacroRefreshService::class)->refresh();
        } catch (RuntimeException) {
        }
        $this->assertSame('stale', MacroBrief::latest('generated_at')->first()->status);
    }
}
