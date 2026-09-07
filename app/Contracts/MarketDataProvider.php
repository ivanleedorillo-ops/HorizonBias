<?php

namespace App\Contracts;

interface MarketDataProvider
{
    /** @return array<\App\Data\Candle> */
    public function fetch(string $timeframe): array;

    public function name(): string;
}
