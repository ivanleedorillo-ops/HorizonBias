<?php

namespace Tests\Unit;

use App\Services\Market\CandlePeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CandlePeriodTest extends TestCase
{
    #[Test]
    public function it_calculates_the_completion_time_for_every_supported_timeframe(): void
    {
        $openedAt = CarbonImmutable::parse('2026-01-01T00:00:00Z');

        $this->assertSame('2026-01-01T00:05:00+00:00', CandlePeriod::closesAt($openedAt, '5m')->toIso8601String());
        $this->assertSame('2026-01-01T00:15:00+00:00', CandlePeriod::closesAt($openedAt, '15m')->toIso8601String());
        $this->assertSame('2026-01-01T01:00:00+00:00', CandlePeriod::closesAt($openedAt, '1h')->toIso8601String());
        $this->assertSame('2026-01-01T04:00:00+00:00', CandlePeriod::closesAt($openedAt, '4h')->toIso8601String());
        $this->assertSame('2026-01-02T00:00:00+00:00', CandlePeriod::closesAt($openedAt, '1d')->toIso8601String());
        $this->assertSame('2026-01-08T00:00:00+00:00', CandlePeriod::closesAt($openedAt, '1w')->toIso8601String());
        $this->assertSame('2026-02-01T00:00:00+00:00', CandlePeriod::closesAt($openedAt, '1mo')->toIso8601String());
    }
}
