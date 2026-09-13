<?php

namespace App\Services\History;

use App\Models\BiasHistoryPoint;
use App\Models\BiasOutcome;
use App\Models\MarketCandle;

final class OutcomeEvaluator
{
    public function evaluate(?string $scope = null, int $limit = 1000): array
    {
        $query = BiasHistoryPoint::query()
            ->where('status', 'ready')
            ->where(function ($query) {
                $query->whereDoesntHave('outcomes')
                    ->orWhereHas('outcomes', fn ($outcomes) => $outcomes->where('status', 'pending'));
            })
            ->orderBy('id');
        if ($scope !== null) {
            $query->where('scope', $scope);
        }

        $evaluated = 0;
        $pending = 0;
        $unavailable = 0;

        foreach ($query->limit($limit)->get() as $point) {
            foreach ($this->horizonsFor($point->scope) as $minutes) {
                $outcome = BiasOutcome::query()->firstOrCreate(
                    ['bias_history_point_id' => $point->id, 'horizon_minutes' => $minutes],
                    [
                        'target_at' => $point->data_as_of->addMinutes($minutes),
                        'baseline_close' => (float) ($point->metrics['close'] ?? 0),
                        'atr14' => isset($point->metrics['atr14']) ? (float) $point->metrics['atr14'] : null,
                        'status' => 'pending',
                    ],
                );

                if ($outcome->status === 'evaluated') {
                    continue;
                }
                if ($outcome->target_at->isFuture()) {
                    $pending++;

                    continue;
                }

                $baseline = (float) $outcome->baseline_close;
                $atr = (float) $outcome->atr14;
                if (! is_finite($baseline) || ! is_finite($atr) || $baseline <= 0 || $atr <= 0) {
                    $outcome->update(['status' => 'unavailable', 'evaluated_at' => now('UTC')]);
                    $unavailable++;

                    continue;
                }

                $candleTimeframe = config("horizon.history.outcome_candle_timeframes.{$point->scope}");
                $future = MarketCandle::query()
                    ->where('symbol', $point->symbol)
                    ->where('timeframe', $candleTimeframe)
                    ->where('opened_at', '>=', $outcome->target_at)
                    ->oldest('opened_at')
                    ->first();
                if (! $future) {
                    $pending++;

                    continue;
                }

                $futureClose = (float) $future->close;
                $move = $futureClose - $baseline;
                $normalizedMove = $move / $atr;
                $threshold = max(0.0, (float) config('horizon.history.neutral_atr_threshold', 0.25));
                $actualDirection = $normalizedMove >= $threshold
                    ? 'bullish'
                    : ($normalizedMove <= -$threshold ? 'bearish' : 'neutral');

                $outcome->update([
                    'observed_at' => $future->opened_at,
                    'future_close' => $futureClose,
                    'forward_return_percent' => (($futureClose - $baseline) / $baseline) * 100,
                    'normalized_move' => $normalizedMove,
                    'actual_direction' => $actualDirection,
                    'aligned' => $actualDirection === $this->expectedDirection($point->label),
                    'status' => 'evaluated',
                    'evaluated_at' => now('UTC'),
                ]);
                $evaluated++;
            }
        }

        return compact('evaluated', 'pending', 'unavailable');
    }

    private function horizonsFor(string $scope): array
    {
        return array_map('intval', config("horizon.history.outcome_horizons.{$scope}", []));
    }

    private function expectedDirection(string $label): string
    {
        $label = strtolower($label);

        return str_contains($label, 'bullish') ? 'bullish' : (str_contains($label, 'bearish') ? 'bearish' : 'neutral');
    }
}
