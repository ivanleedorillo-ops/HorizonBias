<?php

namespace App\Services\Analysis;

use App\Data\Candle;

final class IndicatorCalculator
{
    public function calculate(array $candles): array
    {
        $closes = array_map(fn (Candle $c) => $c->close, $candles);
        $ema20 = $this->emaSeries($closes, 20);
        $ema50 = $this->emaSeries($closes, 50);
        $ema200 = $this->emaSeries($closes, 200);
        $macd = $this->macd($closes);

        return [
            'close' => $closes === [] ? null : end($closes),
            'ema20' => $this->lastValue($ema20),
            'ema50' => $this->lastValue($ema50),
            'ema200' => $this->lastValue($ema200),
            'rsi14' => $this->rsi($closes, 14),
            'macd' => $macd['macd'],
            'macd_signal' => $macd['signal'],
            'macd_histogram' => isset($macd['macd'], $macd['signal']) ? $macd['macd'] - $macd['signal'] : null,
            'roc10' => $this->roc($closes, 10),
            'atr14' => $this->atr($candles, 14),
            'adx14' => $this->adx($candles, 14),
        ];
    }

    public function ema(array $values, int $period): ?float
    {
        return $this->lastValue($this->emaSeries($values, $period));
    }

    public function emaSeries(array $values, int $period): array
    {
        if ($period < 1 || count($values) < $period || ! $this->validNumbers($values)) {
            return [];
        }

        $series = array_fill(0, count($values), null);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        $series[$period - 1] = $ema;
        $multiplier = 2 / ($period + 1);

        for ($i = $period; $i < count($values); $i++) {
            $ema = (($values[$i] - $ema) * $multiplier) + $ema;
            $series[$i] = $ema;
        }

        return $series;
    }

    public function rsi(array $closes, int $period = 14): ?float
    {
        if (count($closes) <= $period || ! $this->validNumbers($closes)) {
            return null;
        }

        $gains = $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains += max($change, 0);
            $losses += max(-$change, 0);
        }
        $averageGain = $gains / $period;
        $averageLoss = $losses / $period;

        for ($i = $period + 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $averageGain = (($averageGain * ($period - 1)) + max($change, 0)) / $period;
            $averageLoss = (($averageLoss * ($period - 1)) + max(-$change, 0)) / $period;
        }

        if ($averageLoss == 0.0) {
            return $averageGain == 0.0 ? 50.0 : 100.0;
        }

        return 100 - (100 / (1 + ($averageGain / $averageLoss)));
    }

    public function macd(array $closes, int $fast = 12, int $slow = 26, int $signalPeriod = 9): array
    {
        $fastSeries = $this->emaSeries($closes, $fast);
        $slowSeries = $this->emaSeries($closes, $slow);
        if ($fastSeries === [] || $slowSeries === []) {
            return ['macd' => null, 'signal' => null];
        }

        $macdValues = [];
        foreach ($closes as $index => $_) {
            if (isset($fastSeries[$index], $slowSeries[$index])) {
                $macdValues[] = $fastSeries[$index] - $slowSeries[$index];
            }
        }
        $signal = $this->ema($macdValues, $signalPeriod);

        return ['macd' => $macdValues === [] ? null : end($macdValues), 'signal' => $signal];
    }

    public function roc(array $closes, int $period = 10): ?float
    {
        if (count($closes) <= $period || ! $this->validNumbers($closes)) {
            return null;
        }
        $previous = $closes[count($closes) - 1 - $period];
        if ($previous == 0.0) {
            return null;
        }

        return (($closes[array_key_last($closes)] - $previous) / $previous) * 100;
    }

    public function atr(array $candles, int $period = 14): ?float
    {
        $ranges = $this->trueRanges($candles);
        if (count($ranges) < $period) {
            return null;
        }
        $atr = array_sum(array_slice($ranges, 0, $period)) / $period;
        for ($i = $period; $i < count($ranges); $i++) {
            $atr = (($atr * ($period - 1)) + $ranges[$i]) / $period;
        }

        return $atr;
    }

    public function adx(array $candles, int $period = 14): ?float
    {
        if (count($candles) < ($period * 2) + 1) {
            return null;
        }

        $trs = $plusDms = $minusDms = [];
        for ($i = 1; $i < count($candles); $i++) {
            $upMove = $candles[$i]->high - $candles[$i - 1]->high;
            $downMove = $candles[$i - 1]->low - $candles[$i]->low;
            $trs[] = max(
                $candles[$i]->high - $candles[$i]->low,
                abs($candles[$i]->high - $candles[$i - 1]->close),
                abs($candles[$i]->low - $candles[$i - 1]->close),
            );
            $plusDms[] = $upMove > $downMove && $upMove > 0 ? $upMove : 0.0;
            $minusDms[] = $downMove > $upMove && $downMove > 0 ? $downMove : 0.0;
        }

        $smoothedTr = array_sum(array_slice($trs, 0, $period));
        $smoothedPlus = array_sum(array_slice($plusDms, 0, $period));
        $smoothedMinus = array_sum(array_slice($minusDms, 0, $period));
        $dx = [];

        for ($i = $period - 1; $i < count($trs); $i++) {
            if ($i >= $period) {
                $smoothedTr = $smoothedTr - ($smoothedTr / $period) + $trs[$i];
                $smoothedPlus = $smoothedPlus - ($smoothedPlus / $period) + $plusDms[$i];
                $smoothedMinus = $smoothedMinus - ($smoothedMinus / $period) + $minusDms[$i];
            }
            if ($smoothedTr == 0.0) {
                $dx[] = 0.0;
                continue;
            }
            $plusDi = 100 * ($smoothedPlus / $smoothedTr);
            $minusDi = 100 * ($smoothedMinus / $smoothedTr);
            $sum = $plusDi + $minusDi;
            $dx[] = $sum == 0.0 ? 0.0 : 100 * abs($plusDi - $minusDi) / $sum;
        }

        if (count($dx) < $period) {
            return null;
        }
        $adx = array_sum(array_slice($dx, 0, $period)) / $period;
        for ($i = $period; $i < count($dx); $i++) {
            $adx = (($adx * ($period - 1)) + $dx[$i]) / $period;
        }

        return $adx;
    }

    public function pivots(array $candles): array
    {
        $highs = $lows = [];
        for ($i = 2; $i < count($candles) - 2; $i++) {
            $high = $candles[$i]->high;
            $low = $candles[$i]->low;
            if ($high > $candles[$i - 1]->high && $high > $candles[$i - 2]->high && $high > $candles[$i + 1]->high && $high > $candles[$i + 2]->high) {
                $highs[] = ['index' => $i, 'value' => $high];
            }
            if ($low < $candles[$i - 1]->low && $low < $candles[$i - 2]->low && $low < $candles[$i + 1]->low && $low < $candles[$i + 2]->low) {
                $lows[] = ['index' => $i, 'value' => $low];
            }
        }

        return ['highs' => $highs, 'lows' => $lows];
    }

    private function trueRanges(array $candles): array
    {
        $ranges = [];
        for ($i = 1; $i < count($candles); $i++) {
            $ranges[] = max(
                $candles[$i]->high - $candles[$i]->low,
                abs($candles[$i]->high - $candles[$i - 1]->close),
                abs($candles[$i]->low - $candles[$i - 1]->close),
            );
        }

        return $ranges;
    }

    private function validNumbers(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                return false;
            }
        }
        return true;
    }

    private function lastValue(array $series): ?float
    {
        if ($series === []) {
            return null;
        }
        $value = end($series);
        return is_float($value) || is_int($value) ? (float) $value : null;
    }
}
