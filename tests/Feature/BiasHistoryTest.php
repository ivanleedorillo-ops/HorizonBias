<?php

namespace Tests\Feature;

use App\Models\BiasHistoryPoint;
use App\Models\BiasOutcome;
use App\Models\BiasSnapshot;
use App\Models\MarketCandle;
use App\Services\History\BiasHistoryService;
use App\Services\History\OutcomeEvaluator;
use App\Services\History\ReliabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiasHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['horizon.market_mode' => 'live']);
    }

    #[Test]
    public function repeated_snapshots_for_one_completed_candle_create_one_canonical_point(): void
    {
        $service = app(BiasHistoryService::class);
        $first = $this->snapshot('1h', 20, now('UTC')->subHour());
        $second = $this->snapshot('1h', 45, $first->data_as_of, now('UTC')->addMinute());

        $service->captureTimeframe($first);
        $service->captureTimeframe($second);

        $this->assertDatabaseCount('bias_history_points', 1);
        $this->assertDatabaseHas('bias_history_points', ['scope' => '1h', 'score' => 45]);
        $this->assertSame([$second->id], BiasHistoryPoint::first()->source_snapshot_ids);
    }

    #[Test]
    public function overall_history_requires_core_timeframes_and_records_its_sources(): void
    {
        $service = app(BiasHistoryService::class);
        $generatedAt = CarbonImmutable::now('UTC');
        foreach (['5m', '1h', '4h', '1d'] as $scope) {
            $service->captureTimeframe($this->snapshot($scope, 20, $generatedAt->subMinutes(5), $generatedAt));
        }

        $overall = $service->captureOverallAt('XAU/USD', $generatedAt);

        $this->assertNotNull($overall);
        $this->assertSame('overall', $overall->scope);
        $this->assertSame(20, $overall->score);
        $this->assertCount(4, $overall->source_snapshot_ids);
    }

    #[Test]
    public function evaluator_uses_only_a_future_stored_candle_and_atr_neutral_band(): void
    {
        $point = $this->historyPoint('15m', 35, 'Bullish', now('UTC')->subHours(2), ['close' => 100, 'atr14' => 2]);
        MarketCandle::create([
            'provider' => 'Fake', 'symbol' => 'XAU/USD', 'timeframe' => '15m',
            'opened_at' => $point->data_as_of->addMinutes(45),
            'open' => 100, 'high' => 201, 'low' => 99, 'close' => 200, 'volume' => null,
        ]);
        MarketCandle::create([
            'provider' => 'Fake', 'symbol' => 'XAU/USD', 'timeframe' => '15m',
            'opened_at' => $point->data_as_of->addHour(),
            'open' => 100, 'high' => 102, 'low' => 99, 'close' => 101, 'volume' => null,
        ]);

        $result = app(OutcomeEvaluator::class)->evaluate('15m');

        $this->assertSame(1, $result['evaluated']);
        $outcome = BiasOutcome::first();
        $this->assertSame('evaluated', $outcome->status);
        $this->assertSame('bullish', $outcome->actual_direction);
        $this->assertTrue($outcome->aligned);
        $this->assertSame(0.5, $outcome->normalized_move);
        $this->assertSame($point->data_as_of->addHour()->toIso8601String(), $outcome->observed_at->toIso8601String());
    }

    #[Test]
    public function evaluator_marks_zero_atr_unavailable_instead_of_inventing_an_outcome(): void
    {
        $this->historyPoint('15m', 0, 'Neutral', now('UTC')->subHours(2), ['close' => 100, 'atr14' => 0]);

        $result = app(OutcomeEvaluator::class)->evaluate('15m');

        $this->assertSame(1, $result['unavailable']);
        $this->assertDatabaseHas('bias_outcomes', ['status' => 'unavailable', 'aligned' => null]);
    }

    #[Test]
    public function reliability_hides_alignment_until_the_minimum_sample_is_reached(): void
    {
        config(['horizon.history.minimum_samples' => 3, 'horizon.history.established_samples' => 5]);
        for ($index = 0; $index < 2; $index++) {
            $point = $this->historyPoint('overall', 25, 'Bullish', now('UTC')->subHours(12 - ($index * 5)), ['close' => 100, 'atr14' => 2]);
            BiasOutcome::create([
                'bias_history_point_id' => $point->id, 'horizon_minutes' => 240,
                'target_at' => $point->data_as_of->addHours(4), 'observed_at' => $point->data_as_of->addHours(4),
                'baseline_close' => 100, 'future_close' => 101, 'atr14' => 2,
                'forward_return_percent' => 1, 'normalized_move' => 0.5,
                'actual_direction' => 'bullish', 'aligned' => true, 'status' => 'evaluated', 'evaluated_at' => now('UTC'),
            ]);
        }

        $insufficient = app(ReliabilityService::class)->data('7d', 'overall');
        $this->assertNull($insufficient['summary']['alignment_percent']);
        $this->assertSame('insufficient', $insufficient['summary']['sample_quality']);

        $point = $this->historyPoint('overall', -25, 'Bearish', now('UTC')->subHours(2), ['close' => 100, 'atr14' => 2]);
        BiasOutcome::create([
            'bias_history_point_id' => $point->id, 'horizon_minutes' => 240,
            'target_at' => $point->data_as_of->addHours(4), 'observed_at' => now('UTC'),
            'baseline_close' => 100, 'future_close' => 99, 'atr14' => 2,
            'forward_return_percent' => -1, 'normalized_move' => -0.5,
            'actual_direction' => 'bearish', 'aligned' => true, 'status' => 'evaluated', 'evaluated_at' => now('UTC'),
        ]);

        $limited = app(ReliabilityService::class)->data('7d', 'overall');
        $this->assertSame(100.0, $limited['summary']['alignment_percent']);
        $this->assertSame('limited', $limited['summary']['sample_quality']);
        $this->assertCount(2, $limited['summary']['alignment_interval_95']);
    }

    private function snapshot(string $scope, int $score, mixed $dataAsOf, mixed $generatedAt = null): BiasSnapshot
    {
        return BiasSnapshot::create([
            'symbol' => 'XAU/USD', 'timeframe' => $scope, 'score' => $score,
            'label' => $score >= 20 ? 'Bullish' : ($score <= -20 ? 'Bearish' : 'Neutral'),
            'component_scores' => ['trend' => $score, 'momentum' => 0, 'structure' => 0, 'breakout' => 0],
            'metrics' => ['close' => 100, 'atr14' => 2], 'explanations' => [], 'provider' => 'Fake',
            'data_as_of' => $dataAsOf, 'generated_at' => $generatedAt ?? now('UTC'), 'status' => 'ready',
        ]);
    }

    private function historyPoint(string $scope, int $score, string $label, mixed $at, array $metrics): BiasHistoryPoint
    {
        return BiasHistoryPoint::create([
            'symbol' => 'XAU/USD', 'scope' => $scope, 'score' => $score, 'label' => $label,
            'component_scores' => [], 'metrics' => $metrics, 'source_snapshot_ids' => [],
            'source_hash' => hash('sha256', $scope.'|'.$at.'|'.uniqid('', true)),
            'data_as_of' => $at, 'generated_at' => $at, 'status' => 'ready',
        ]);
    }
}
