<?php

namespace Tests\Support;

use App\Data\Candle;
use Carbon\CarbonImmutable;

trait MakesCandles
{
    protected function candles(int $count = 250, float $start = 1800, float $step = 1.0): array
    {
        return array_map(function (int $index) use ($start, $step) {
            $close = $start + ($index * $step);
            return new Candle(CarbonImmutable::parse('2025-01-01', 'UTC')->addHours($index), $close - ($step / 2), $close + 0.4, $close - 0.4, $close, 1000 + $index);
        }, range(0, $count - 1));
    }
}
