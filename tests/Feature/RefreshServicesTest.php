<?php

namespace Tests\Feature;

use App\Contracts\MacroContextProvider;
use App\Contracts\MarketDataProvider;
use App\Models\BiasSnapshot;
use App\Models\MacroBrief;
use App\Services\Analysis\BiasScorer;
use App\Services\Analysis\IndicatorCalculator;
use App\Services\Macro\MacroRefreshService;
use App\Services\Market\MarketMode;
use App\Services\Market\MarketRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\MakesCandles;
use Tests\TestCase;

class RefreshServicesTest extends TestCase
{
    use MakesCandles, RefreshDatabase;

    #[Test] public function market_refresh_persists_candles_and_a_snapshot_idempotently(): void
    {
        config(['horizon.market_mode' => 'live']);
        $provider = new class($this->candles()) implements MarketDataProvider {
            public function __construct(private array $candles) {}
            public function fetch(string $timeframe): array { return $this->candles; }
            public function name(): string { return 'Fake'; }
        };
        $service = new MarketRefreshService($provider, new BiasScorer(new IndicatorCalculator), new MarketMode);
        $service->refresh('1h');
        $service->refresh('1h');
        $this->assertDatabaseCount('market_candles', 250);
        $this->assertDatabaseCount('bias_snapshots', 2);
        $this->assertDatabaseHas('bias_snapshots', ['timeframe' => '1h', 'score' => 65, 'status' => 'ready']);
    }

    #[Test] public function a_market_failure_marks_the_last_good_snapshot_stale(): void
    {
        config(['horizon.market_mode' => 'live']);
        BiasSnapshot::create(['symbol' => 'XAU/USD', 'timeframe' => '1h', 'score' => 20, 'label' => 'Bullish', 'component_scores' => [], 'metrics' => [], 'explanations' => [], 'provider' => 'Fake', 'data_as_of' => now(), 'generated_at' => now(), 'status' => 'ready']);
        $provider = new class implements MarketDataProvider {
            public function fetch(string $timeframe): array { throw new RuntimeException('offline'); }
            public function name(): string { return 'Fake'; }
        };
        try { (new MarketRefreshService($provider, new BiasScorer(new IndicatorCalculator), new MarketMode))->refresh('1h'); } catch (RuntimeException) {}
        $this->assertDatabaseHas('bias_snapshots', ['timeframe' => '1h', 'status' => 'stale']);
    }

    #[Test] public function macro_refresh_persists_sources_and_marks_fallback_stale(): void
    {
        $valid = new class implements MacroContextProvider {
            public function generate(array $technicalContext): array { return ['stance' => 'mixed', 'risk_level' => 'medium', 'summary' => 'Mixed forces.', 'events' => [['headline' => 'Event', 'why_it_matters' => 'Context', 'direction' => 'mixed', 'published_at' => null, 'source_name' => 'Source', 'source_url' => 'https://example.com']]]; }
            public function name(): string { return 'Fake AI'; }
        };
        (new MacroRefreshService($valid))->refresh();
        $this->assertDatabaseHas('macro_briefs', ['stance' => 'mixed', 'status' => 'ready']);

        $failing = new class implements MacroContextProvider {
            public function generate(array $technicalContext): array { throw new RuntimeException('offline'); }
            public function name(): string { return 'Fake AI'; }
        };
        try { (new MacroRefreshService($failing))->refresh(); } catch (RuntimeException) {}
        $this->assertSame('stale', MacroBrief::latest('generated_at')->first()->status);
    }
}
