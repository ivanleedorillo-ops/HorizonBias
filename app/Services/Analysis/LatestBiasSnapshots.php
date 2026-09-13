<?php

namespace App\Services\Analysis;

use App\Models\BiasSnapshot;
use Illuminate\Support\Collection;

final class LatestBiasSnapshots
{
    public function forSymbol(string $symbol): Collection
    {
        $latestIds = BiasSnapshot::query()
            ->selectRaw('MAX(id)')
            ->where('symbol', $symbol)
            ->groupBy('timeframe');

        return BiasSnapshot::query()
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy('timeframe');
    }
}
