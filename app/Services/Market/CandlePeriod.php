<?php

namespace App\Services\Market;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CandlePeriod
{
    public static function closesAt(CarbonImmutable $openedAt, string $timeframe): CarbonImmutable
    {
        return match ($timeframe) {
            '5m' => $openedAt->addMinutes(5),
            '15m' => $openedAt->addMinutes(15),
            '1h' => $openedAt->addHour(),
            '4h' => $openedAt->addHours(4),
            '1d' => $openedAt->addDay(),
            '1w' => $openedAt->addWeek(),
            '1mo' => $openedAt->addMonthNoOverflow(),
            default => throw new InvalidArgumentException("Unsupported timeframe: {$timeframe}"),
        };
    }
}
