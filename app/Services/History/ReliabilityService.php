<?php

namespace App\Services\History;

use App\Models\BiasHistoryPoint;
use App\Models\BiasOutcome;
use App\Services\Market\MarketMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReliabilityService
{
    private const RANGE_MINUTES = [
        '24h' => 1440,
        '7d' => 10080,
        '30d' => 43200,
        '90d' => 129600,
        '1y' => 525600,
    ];

    public function __construct(private readonly MarketMode $mode) {}

    public function data(string $range = '7d', string $scope = 'overall'): array
    {
        if ($this->mode->effective() === 'demo') {
            return $this->demo($range, $scope);
        }

        $since = CarbonImmutable::now('UTC')->subMinutes(self::RANGE_MINUTES[$range] ?? self::RANGE_MINUTES['7d']);
        $series = $this->sampledSeries($scope, $since);
        $summary = $this->summaryFor($scope, $since);
        $changes = $this->changesFor($scope, $since);
        $status = $series === [] ? 'unavailable' : ($summary['sample_quality'] === 'insufficient' ? 'insufficient' : 'ready');

        return [
            'mode' => 'live',
            'range' => $range,
            'scope' => $scope,
            'availability' => [
                'status' => $status,
                'message' => $this->availabilityMessage($status, $summary['mature_samples']),
            ],
            'summary' => $summary,
            'series' => $series,
            'heatmap' => $this->heatmap($since),
            'changes' => $changes,
            'methodology' => [
                'neutral_atr_threshold' => (float) config('horizon.history.neutral_atr_threshold', 0.25),
                'minimum_samples' => (int) config('horizon.history.minimum_samples', 20),
                'statement' => 'Historical alignment compares the original closed-bar bias with a later ATR-normalized price direction. It is not a profitability backtest.',
            ],
            'generated_at' => now('UTC')->toIso8601String(),
        ];
    }

    public function contextForAi(): array
    {
        if ($this->mode->effective() === 'demo') {
            return ['available' => false, 'sample_quality' => 'insufficient', 'reason' => 'Demo history is illustrative.'];
        }

        $since = CarbonImmutable::now('UTC')->subDays(90);
        $overall = $this->summaryFor('overall', $since);
        $timeframes = [];
        foreach (array_keys(config('horizon.timeframes')) as $scope) {
            $summary = $this->summaryFor($scope, $since);
            $timeframes[$scope] = [
                'mature_samples' => $summary['mature_samples'],
                'alignment_percent' => $summary['alignment_percent'],
                'sample_quality' => $summary['sample_quality'],
                'flip_count' => $summary['flip_count'],
            ];
        }

        return [
            'available' => BiasHistoryPoint::query()->where('scope', 'overall')->exists(),
            'window' => '90d',
            'method' => 'ATR-normalized closed-bar directional alignment; not a trading-performance backtest.',
            'overall' => $overall,
            'timeframes' => $timeframes,
            'recent_changes' => $this->changesFor('overall', $since),
        ];
    }

    private function sampledSeries(string $scope, CarbonImmutable $since): array
    {
        $query = $this->pointsQuery($scope, $since)->orderBy('generated_at')->orderBy('id');
        $count = (clone $query)->count();
        if ($count === 0) {
            return [];
        }

        $maximum = max(30, (int) config('horizon.history.max_chart_points', 360));
        $step = max(1, (int) ceil($count / $maximum));
        $series = [];
        foreach ($query->cursor() as $index => $point) {
            if ($index % $step !== 0 && $index !== $count - 1) {
                continue;
            }
            $series[] = [
                'at' => $point->generated_at->toIso8601String(),
                'data_as_of' => $point->data_as_of->toIso8601String(),
                'score' => $point->score,
                'label' => $point->label,
                'stale' => $point->status !== 'ready',
            ];
        }

        return $series;
    }

    private function summaryFor(string $scope, CarbonImmutable $since): array
    {
        $primaryHorizon = (int) (config("horizon.history.outcome_horizons.{$scope}.0") ?? 0);
        ['mature' => $mature, 'aligned' => $aligned] = $this->nonOverlappingOutcomeStats($scope, $primaryHorizon, $since);
        $pending = BiasOutcome::query()
            ->where('status', 'pending')
            ->where('horizon_minutes', $primaryHorizon)
            ->whereHas('historyPoint', fn (Builder $query) => $query
                ->where('symbol', config('horizon.symbol'))
                ->where('scope', $scope)
                ->where('generated_at', '>=', $since))
            ->count();
        $minimum = max(1, (int) config('horizon.history.minimum_samples', 20));
        $established = max($minimum, (int) config('horizon.history.established_samples', 50));
        $quality = $mature < $minimum ? 'insufficient' : ($mature < $established ? 'limited' : 'established');
        $alignment = $mature >= $minimum ? round($aligned / $mature * 100, 1) : null;
        $interval = $mature >= $minimum ? $this->wilsonInterval($aligned, $mature) : null;
        $points = $this->pointsQuery($scope, $since)->orderBy('generated_at')->get(['label', 'generated_at']);

        return [
            'evaluation_horizon_minutes' => $primaryHorizon,
            'mature_samples' => $mature,
            'pending_samples' => $pending,
            'aligned_samples' => $aligned,
            'alignment_percent' => $alignment,
            'alignment_interval_95' => $interval,
            'sample_quality' => $quality,
            'flip_count' => $this->flipCount($points->pluck('label')->all()),
            'current_bias_duration_minutes' => $this->currentDuration($scope),
        ];
    }

    private function changesFor(string $scope, CarbonImmutable $since): array
    {
        $points = $this->pointsQuery($scope, $since)->latest('generated_at')->latest('id')->limit(2)->get();
        if ($points->count() < 2) {
            return [];
        }

        $current = $points[0];
        $previous = $points[1];
        $changes = [[
            'type' => $current->label === $previous->label ? 'score' : 'label',
            'headline' => $current->label === $previous->label
                ? "{$scope} score moved from {$previous->score} to {$current->score}."
                : "{$scope} changed from {$previous->label} to {$current->label}.",
            'score_change' => $current->score - $previous->score,
            'at' => $current->generated_at->toIso8601String(),
        ]];

        foreach (['trend', 'momentum', 'structure', 'breakout'] as $component) {
            $before = $previous->component_scores[$component] ?? null;
            $after = $current->component_scores[$component] ?? null;
            if (is_numeric($before) && is_numeric($after) && (int) $before !== (int) $after) {
                $changes[] = [
                    'type' => 'component',
                    'headline' => ucfirst($component)." changed from {$before} to {$after}.",
                    'score_change' => (int) $after - (int) $before,
                    'at' => $current->generated_at->toIso8601String(),
                ];
            }
        }

        return array_slice($changes, 0, 5);
    }

    private function heatmap(CarbonImmutable $since): array
    {
        $rows = [];
        foreach (array_keys(config('horizon.timeframes')) as $scope) {
            $points = $this->pointsQuery($scope, $since)->latest('generated_at')->limit(60)->get()->reverse()->values();
            $rows[] = [
                'scope' => $scope,
                'label' => config("horizon.timeframes.{$scope}.label"),
                'points' => $points->map(fn (BiasHistoryPoint $point) => [
                    'at' => $point->generated_at->toIso8601String(),
                    'score' => $point->score,
                    'label' => $point->label,
                ])->all(),
            ];
        }

        return $rows;
    }

    private function pointsQuery(string $scope, CarbonImmutable $since): Builder
    {
        return BiasHistoryPoint::query()
            ->where('symbol', config('horizon.symbol'))
            ->where('scope', $scope)
            ->where('generated_at', '>=', $since);
    }

    private function flipCount(array $labels): int
    {
        $flips = 0;
        for ($index = 1; $index < count($labels); $index++) {
            if ($labels[$index] !== $labels[$index - 1]) {
                $flips++;
            }
        }

        return $flips;
    }

    private function currentDuration(string $scope): ?int
    {
        $base = BiasHistoryPoint::query()->where('symbol', config('horizon.symbol'))->where('scope', $scope);
        $latest = (clone $base)->latest('generated_at')->latest('id')->first(['label', 'generated_at']);
        if ($latest === null) {
            return null;
        }
        $previousDifferent = (clone $base)
            ->where('generated_at', '<', $latest->generated_at)
            ->where('label', '!=', $latest->label)
            ->latest('generated_at')
            ->first(['generated_at']);
        $streak = (clone $base)->where('label', $latest->label);
        if ($previousDifferent) {
            $streak->where('generated_at', '>', $previousDifferent->generated_at);
        }
        $startedAt = $streak->oldest('generated_at')->value('generated_at');

        return $startedAt ? (int) CarbonImmutable::parse($startedAt)->diffInMinutes($latest->generated_at) : 0;
    }

    private function wilsonInterval(int $successes, int $total): array
    {
        $z = 1.96;
        $rate = $successes / $total;
        $denominator = 1 + ($z ** 2 / $total);
        $center = ($rate + ($z ** 2 / (2 * $total))) / $denominator;
        $margin = ($z * sqrt(($rate * (1 - $rate) / $total) + ($z ** 2 / (4 * $total ** 2)))) / $denominator;

        return [round(max(0, $center - $margin) * 100, 1), round(min(1, $center + $margin) * 100, 1)];
    }

    private function nonOverlappingOutcomeStats(string $scope, int $horizon, CarbonImmutable $since): array
    {
        $rows = DB::table('bias_outcomes')
            ->join('bias_history_points', 'bias_history_points.id', '=', 'bias_outcomes.bias_history_point_id')
            ->where('bias_outcomes.status', 'evaluated')
            ->where('bias_outcomes.horizon_minutes', $horizon)
            ->where('bias_history_points.symbol', config('horizon.symbol'))
            ->where('bias_history_points.scope', $scope)
            ->where('bias_history_points.generated_at', '>=', $since)
            ->orderBy('bias_history_points.data_as_of')
            ->orderBy('bias_history_points.id')
            ->select(['bias_outcomes.target_at', 'bias_outcomes.aligned', 'bias_history_points.data_as_of'])
            ->cursor();

        $mature = 0;
        $aligned = 0;
        $nextEligibleAt = null;
        foreach ($rows as $row) {
            $sourceAt = CarbonImmutable::parse($row->data_as_of)->utc();
            if ($nextEligibleAt && $sourceAt->lt($nextEligibleAt)) {
                continue;
            }
            $mature++;
            $aligned += (int) (bool) $row->aligned;
            $nextEligibleAt = CarbonImmutable::parse($row->target_at)->utc();
        }

        return compact('mature', 'aligned');
    }

    private function availabilityMessage(string $status, int $samples): string
    {
        return match ($status) {
            'ready' => "Historical alignment is based on {$samples} mature observations.",
            'insufficient' => "Only {$samples} mature observations are available; no reliability percentage is shown yet.",
            default => 'History will appear after canonical bias snapshots are captured.',
        };
    }

    private function demo(string $range, string $scope): array
    {
        $scores = [18, 24, 31, 27, 42, 38, 46, 51, 44, 48];
        $series = collect($scores)->map(fn (int $score, int $index) => [
            'at' => CarbonImmutable::parse('2026-09-01T12:00:00Z')->addHours($index * 4)->toIso8601String(),
            'data_as_of' => CarbonImmutable::parse('2026-09-01T12:00:00Z')->addHours($index * 4)->toIso8601String(),
            'score' => $score,
            'label' => $score >= 20 ? 'Bullish' : 'Neutral',
            'stale' => false,
        ])->all();

        return [
            'mode' => 'demo', 'range' => $range, 'scope' => $scope,
            'availability' => ['status' => 'demo', 'message' => 'Illustrative history — not live market analysis.'],
            'summary' => [
                'evaluation_horizon_minutes' => 240, 'mature_samples' => 8, 'pending_samples' => 2,
                'aligned_samples' => 5, 'alignment_percent' => null, 'alignment_interval_95' => null,
                'sample_quality' => 'insufficient', 'flip_count' => 2, 'current_bias_duration_minutes' => 480,
            ],
            'series' => $series,
            'heatmap' => [],
            'changes' => [['type' => 'score', 'headline' => 'Illustrative overall score moved from 44 to 48.', 'score_change' => 4, 'at' => end($series)['at']]],
            'methodology' => [
                'neutral_atr_threshold' => 0.25, 'minimum_samples' => 20,
                'statement' => 'Illustrative historical alignment only — not a profitability backtest.',
            ],
            'generated_at' => '2026-09-03T00:00:00+00:00',
        ];
    }
}
