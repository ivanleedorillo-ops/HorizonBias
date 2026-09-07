<?php

namespace Tests\Unit;

use App\Data\Candle;
use App\Services\Analysis\IndicatorCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MakesCandles;
use Tests\TestCase;

class IndicatorCalculatorTest extends TestCase
{
    use MakesCandles;

    private IndicatorCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new IndicatorCalculator;
    }

    #[Test] public function it_calculates_a_known_ema_series(): void
    {
        $this->assertEqualsWithDelta(4.0, $this->calculator->ema([1, 2, 3, 4, 5], 3), 0.00001);
    }

    #[Test] public function wilder_rsi_handles_rising_falling_and_flat_series(): void
    {
        $this->assertSame(100.0, $this->calculator->rsi(range(1, 30)));
        $this->assertSame(0.0, $this->calculator->rsi(array_reverse(range(1, 30))));
        $this->assertSame(50.0, $this->calculator->rsi(array_fill(0, 30, 10.0)));
    }

    #[Test] public function macd_and_roc_are_zero_for_a_constant_series(): void
    {
        $values = array_fill(0, 60, 100.0);
        $this->assertEqualsWithDelta(0, $this->calculator->macd($values)['macd'], 0.00001);
        $this->assertEqualsWithDelta(0, $this->calculator->macd($values)['signal'], 0.00001);
        $this->assertEqualsWithDelta(0, $this->calculator->roc($values), 0.00001);
    }

    #[Test] public function atr_and_adx_handle_zero_range_and_trending_series(): void
    {
        $flat = array_map(fn ($i) => new Candle(CarbonImmutable::parse('2025-01-01')->addHours($i), 100, 100, 100, 100), range(0, 49));
        $this->assertSame(0.0, $this->calculator->atr($flat));
        $this->assertSame(0.0, $this->calculator->adx($flat));
        $this->assertGreaterThan(90, $this->calculator->adx($this->candles(80)));
    }

    #[Test] public function indicators_return_null_when_history_is_short_or_invalid(): void
    {
        $this->assertNull($this->calculator->rsi([1, 2, 3]));
        $this->assertNull($this->calculator->ema([1, NAN, 3], 3));
        $this->assertNull($this->calculator->roc([0, 1, 2], 2));
    }

    #[Test] public function it_finds_only_confirmed_five_candle_pivots(): void
    {
        $values = [1, 2, 5, 2, 1, 2, -2, 2, 1];
        $candles = array_map(fn ($value, $i) => new Candle(CarbonImmutable::parse('2025-01-01')->addHours($i), $value, $value + .1, $value - .1, $value), $values, array_keys($values));
        $pivots = $this->calculator->pivots($candles);
        $this->assertSame(2, $pivots['highs'][0]['index']);
        $this->assertSame(6, $pivots['lows'][0]['index']);
    }
}
