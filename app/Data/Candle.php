<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class Candle
{
    public function __construct(
        public CarbonImmutable $openedAt,
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public ?float $volume = null,
    ) {}

    public function toArray(): array
    {
        return [
            'opened_at' => $this->openedAt->utc()->toIso8601String(),
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
        ];
    }
}
