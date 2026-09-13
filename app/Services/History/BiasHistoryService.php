<?php

namespace App\Services\History;

use App\Models\BiasHistoryPoint;
use App\Models\BiasSnapshot;
use App\Services\Analysis\BiasScorer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BiasHistoryService
{
    public function __construct(private readonly BiasScorer $scorer) {}

    public function capture(BiasSnapshot $snapshot): BiasHistoryPoint
    {
        $point = $this->captureTimeframe($snapshot);
        $this->captureOverallAt($snapshot->symbol, $snapshot->generated_at);

        return $point;
    }

    public function captureTimeframe(BiasSnapshot $snapshot): BiasHistoryPoint
    {
        $sourceHash = hash('sha256', implode('|', [
            'timeframe-v1',
            $snapshot->symbol,
            $snapshot->timeframe,
            $snapshot->data_as_of->toIso8601String(),
        ]));

        return BiasHistoryPoint::query()->updateOrCreate(
            ['source_hash' => $sourceHash],
            [
                'symbol' => $snapshot->symbol,
                'scope' => $snapshot->timeframe,
                'score' => $snapshot->score,
                'label' => $snapshot->label,
                'component_scores' => $snapshot->component_scores,
                'metrics' => $snapshot->metrics,
                'source_snapshot_ids' => [$snapshot->id],
                'data_as_of' => $snapshot->data_as_of,
                'generated_at' => $snapshot->generated_at,
                'status' => $snapshot->status,
            ],
        );
    }

    public function captureOverallAt(string $symbol, mixed $at): ?BiasHistoryPoint
    {
        $at = CarbonImmutable::parse($at)->utc();
        $latest = $this->latestTimeframesAt($symbol, $at);
        $eligible = $latest->filter(function (BiasHistoryPoint $point) use ($at) {
            $staleAfter = config("horizon.timeframes.{$point->scope}.stale_after");

            return $point->status === 'ready'
                && is_numeric($staleAfter)
                && $point->data_as_of->gte($at->copy()->subMinutes((int) $staleAfter));
        });
        $scores = $eligible->mapWithKeys(fn (BiasHistoryPoint $point) => [$point->scope => $point->score])->all();
        $overall = $this->scorer->overall($scores);
        if (! $overall) {
            return null;
        }

        $sourceIds = $eligible->flatMap(fn (BiasHistoryPoint $point) => $point->source_snapshot_ids ?? [])
            ->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sourceHash = hash('sha256', 'overall-v1|'.$symbol.'|'.implode(',', $sourceIds));
        $pricePoint = $eligible->get('5m') ?? $eligible->get('1h') ?? $eligible->get('1d');
        $atrPoint = $eligible->get('1h') ?? $eligible->get('4h') ?? $eligible->get('1d');
        $dataAsOf = $eligible->max(fn (BiasHistoryPoint $point) => $point->data_as_of->getTimestamp());

        return BiasHistoryPoint::query()->firstOrCreate(
            ['source_hash' => $sourceHash],
            [
                'symbol' => $symbol,
                'scope' => 'overall',
                'score' => $overall['score'],
                'label' => $overall['label'],
                'component_scores' => null,
                'metrics' => [
                    'close' => $pricePoint?->metrics['close'] ?? null,
                    'atr14' => $atrPoint?->metrics['atr14'] ?? null,
                    'timeframe_scores' => $scores,
                ],
                'source_snapshot_ids' => $sourceIds,
                'data_as_of' => now('UTC')->setTimestamp((int) $dataAsOf),
                'generated_at' => $at,
                'status' => 'ready',
            ],
        );
    }

    public function backfill(): array
    {
        $before = BiasHistoryPoint::query()->count();
        BiasSnapshot::query()->orderBy('id')->chunkById(200, function (Collection $snapshots) {
            foreach ($snapshots as $snapshot) {
                $this->captureTimeframe($snapshot);
            }
        });

        BiasHistoryPoint::query()
            ->where('scope', '!=', 'overall')
            ->orderBy('generated_at')
            ->orderBy('id')
            ->each(fn (BiasHistoryPoint $point) => $this->captureOverallAt($point->symbol, $point->generated_at));

        $after = BiasHistoryPoint::query()->count();

        return ['created' => $after - $before, 'total' => $after];
    }

    private function latestTimeframesAt(string $symbol, mixed $at): Collection
    {
        return collect(array_keys(config('horizon.timeframes')))->mapWithKeys(function (string $timeframe) use ($symbol, $at) {
            $point = BiasHistoryPoint::query()
                ->where('symbol', $symbol)
                ->where('scope', $timeframe)
                ->where('generated_at', '<=', $at)
                ->latest('generated_at')
                ->latest('id')
                ->first();

            return $point ? [$timeframe => $point] : [];
        });
    }
}
