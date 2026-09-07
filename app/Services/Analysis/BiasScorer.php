<?php

namespace App\Services\Analysis;

use App\Data\Candle;
use InvalidArgumentException;

final class BiasScorer
{
    public function __construct(private readonly IndicatorCalculator $indicators) {}

    public function score(array $candles): array
    {
        if (count($candles) < 205 || count(array_filter($candles, fn ($c) => $c instanceof Candle)) !== count($candles)) {
            throw new InvalidArgumentException('At least 205 valid candles are required.');
        }

        $metrics = $this->indicators->calculate($candles);
        if (in_array(null, [$metrics['close'], $metrics['ema20'], $metrics['ema50'], $metrics['ema200'], $metrics['rsi14'], $metrics['macd'], $metrics['macd_signal'], $metrics['roc10']], true)) {
            throw new InvalidArgumentException('Indicator calculation returned insufficient data.');
        }

        $trend = $momentum = $structure = $breakout = 0;
        $explanations = [];
        $trend += $this->compare($metrics['close'], $metrics['ema200'], 15);
        $trend += $this->compare($metrics['ema20'], $metrics['ema50'], 10);
        $trend += $this->compare($metrics['ema50'], $metrics['ema200'], 10);
        $explanations[] = $trend > 0 ? 'Moving-average alignment supports the upside.' : ($trend < 0 ? 'Moving-average alignment favors the downside.' : 'Moving averages are balanced.');

        $momentum += $metrics['rsi14'] >= 55 ? 10 : ($metrics['rsi14'] <= 45 ? -10 : 0);
        $momentum += $this->compare($metrics['macd'], $metrics['macd_signal'], 10);
        $momentum += $this->compare($metrics['roc10'], 0, 5);
        $explanations[] = $momentum > 0 ? 'Momentum measures lean positive.' : ($momentum < 0 ? 'Momentum measures lean negative.' : 'Momentum is mixed.');

        $pivots = $this->indicators->pivots($candles);
        if (count($pivots['highs']) >= 2 && count($pivots['lows']) >= 2) {
            $lastHighs = array_slice($pivots['highs'], -2);
            $lastLows = array_slice($pivots['lows'], -2);
            if ($lastHighs[1]['value'] > $lastHighs[0]['value'] && $lastLows[1]['value'] > $lastLows[0]['value']) {
                $structure = 25;
            } elseif ($lastHighs[1]['value'] < $lastHighs[0]['value'] && $lastLows[1]['value'] < $lastLows[0]['value']) {
                $structure = -25;
            }
        }
        $explanations[] = $structure > 0 ? 'Confirmed pivots form a higher-high and higher-low structure.' : ($structure < 0 ? 'Confirmed pivots form a lower-high and lower-low structure.' : 'Confirmed pivots do not show a clean directional structure.');

        $latest = $candles[array_key_last($candles)]->close;
        $previous = array_slice($candles, -21, 20);
        $highest = max(array_map(fn (Candle $c) => $c->high, $previous));
        $lowest = min(array_map(fn (Candle $c) => $c->low, $previous));
        $breakout = $latest > $highest ? 15 : ($latest < $lowest ? -15 : 0);
        $explanations[] = $breakout > 0 ? 'Price closed above the prior 20-candle range.' : ($breakout < 0 ? 'Price closed below the prior 20-candle range.' : 'Price remains inside the prior 20-candle range.');

        $score = max(-100, min(100, $trend + $momentum + $structure + $breakout));

        return [
            'score' => $score,
            'label' => $this->label($score),
            'component_scores' => compact('trend', 'momentum', 'structure', 'breakout'),
            'metrics' => array_map(fn ($value) => is_float($value) ? round($value, 5) : $value, $metrics),
            'explanations' => $explanations,
        ];
    }

    public function label(int|float $score): string
    {
        return match (true) {
            $score >= 60 => 'Strong Bullish',
            $score >= 20 => 'Bullish',
            $score <= -60 => 'Strong Bearish',
            $score <= -20 => 'Bearish',
            default => 'Neutral',
        };
    }

    public function overall(array $scores): ?array
    {
        $required = ['1h', '4h', '1d'];
        if (count($scores) < 4 || array_diff($required, array_keys($scores)) !== []) {
            return null;
        }

        $weighted = $weight = 0.0;
        foreach ($scores as $timeframe => $score) {
            $timeframeWeight = config("horizon.timeframes.{$timeframe}.weight");
            if ($timeframeWeight === null || ! is_numeric($score)) {
                continue;
            }
            $weighted += $score * $timeframeWeight;
            $weight += $timeframeWeight;
        }
        if ($weight === 0.0) {
            return null;
        }
        $score = (int) round($weighted / $weight);

        return ['score' => $score, 'label' => $this->label($score)];
    }

    private function compare(float $left, float $right, int $points): int
    {
        return $left > $right ? $points : ($left < $right ? -$points : 0);
    }
}
