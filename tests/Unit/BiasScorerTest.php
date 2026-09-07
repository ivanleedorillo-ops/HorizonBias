<?php

namespace Tests\Unit;

use App\Services\Analysis\BiasScorer;
use App\Services\Analysis\IndicatorCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MakesCandles;
use Tests\TestCase;

class BiasScorerTest extends TestCase
{
    use MakesCandles;

    private BiasScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new BiasScorer(new IndicatorCalculator);
    }

    public static function labels(): array
    {
        return [[-60, 'Strong Bearish'], [-59, 'Bearish'], [-20, 'Bearish'], [-19, 'Neutral'], [19, 'Neutral'], [20, 'Bullish'], [59, 'Bullish'], [60, 'Strong Bullish']];
    }

    #[DataProvider('labels')] public function it_applies_exact_label_boundaries(int $score, string $label): void
    {
        $this->assertSame($label, $this->scorer->label($score));
    }

    #[Test] public function rising_and_falling_series_produce_directional_scores(): void
    {
        $bullish = $this->scorer->score($this->candles());
        $bearish = $this->scorer->score($this->candles(step: -1));
        $this->assertSame(65, $bullish['score']);
        $this->assertSame(-65, $bearish['score']);
        $this->assertSame('Strong Bullish', $bullish['label']);
        $this->assertSame('Strong Bearish', $bearish['label']);
    }

    #[Test] public function it_rejects_short_or_malformed_series(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->scorer->score(array_fill(0, 205, new \stdClass));
    }

    #[Test] public function it_renormalizes_available_overall_weights(): void
    {
        $result = $this->scorer->overall(['5m' => 100, '1h' => 20, '4h' => 40, '1d' => 60]);
        $this->assertSame(48, $result['score']);
        $this->assertSame('Bullish', $result['label']);
    }

    #[Test] public function overall_requires_four_frames_and_core_horizons(): void
    {
        $this->assertNull($this->scorer->overall(['1h' => 1, '4h' => 1, '1d' => 1]));
        $this->assertNull($this->scorer->overall(['5m' => 1, '15m' => 1, '1h' => 1, '4h' => 1]));
    }
}
