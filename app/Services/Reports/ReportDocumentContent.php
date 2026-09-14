<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

final class ReportDocumentContent
{
    public function blocks(array $report): array
    {
        $blocks = [];
        $add = function (string $text, string $style = 'body') use (&$blocks): void {
            $blocks[] = ['text' => $text, 'style' => $style];
        };

        $add($report['title'], 'title');
        $add('Concise stored decision-support snapshot · '.$report['report_id'], 'subtitle');
        $add('Generated '.$this->time($report['generated_at']).' · '.strtoupper($report['mode']).' mode', 'meta');
        if (! empty($report['notice'])) {
            $add('DATA NOTICE · '.$report['notice'], 'notice');
        }

        $add('EXECUTIVE SUMMARY', 'heading');
        $add(sprintf(
            '%s · %s %s · %s technical bias · data through %s',
            $report['symbol'],
            $this->number($report['quote']['price'] ?? null, 2),
            $report['quote']['currency'] ?? 'USD',
            $report['overall']['label'] ?? 'Unavailable',
            $this->time($report['quote']['completed_at'] ?? $report['quote']['as_of'] ?? null),
        ), 'key');
        $add('Overall technical score: '.$this->score($report['overall']['score'] ?? null).' / 100 · '.(! empty($report['overall']['stale']) ? 'STALE' : ($report['overall'] ? 'READY' : 'UNAVAILABLE')), 'meta');
        foreach ($report['executive']['takeaways'] ?? [] as $takeaway) {
            $add($takeaway, 'bullet');
        }

        $add('SEVEN-TIMEFRAME MATRIX', 'heading');
        if (($report['timeframes'] ?? []) === []) {
            $add('No timeframe snapshots are available.', 'body');
        }
        foreach ($report['timeframes'] ?? [] as $timeframe) {
            $components = $timeframe['component_scores'] ?? [];
            $metrics = $timeframe['metrics'] ?? [];
            $add(sprintf(
                '%s · %s %s · T %s  M %s  S %s  B %s · RSI %s  ADX %s · %s',
                $timeframe['label'] ?? $timeframe['key'] ?? 'Unknown',
                $timeframe['bias'] ?? 'Unavailable',
                $this->score($timeframe['score'] ?? null),
                $this->score($components['trend'] ?? null),
                $this->score($components['momentum'] ?? null),
                $this->score($components['structure'] ?? null),
                $this->score($components['breakout'] ?? null),
                $this->number($metrics['rsi14'] ?? null, 1),
                $this->number($metrics['adx14'] ?? null, 1),
                strtoupper($timeframe['status'] ?? 'unavailable'),
            ), 'matrix');
        }
        $add('T = trend · M = momentum · S = market structure · B = breakout. ATR and ADX are display metrics only.', 'meta');

        $macro = $report['macro'] ?? [];
        $add('DUAL-AI MARKET CONTEXT', 'heading');
        $add(sprintf(
            '%s gold · %s USD · %s · %d%% confidence · %s risk',
            strtoupper($macro['gold_bias'] ?? 'unavailable'),
            strtoupper($macro['usd_strength'] ?? 'unavailable'),
            strtoupper(str_replace('_', ' ', $macro['agreement'] ?? 'unavailable')),
            is_numeric($macro['confidence'] ?? null) ? (int) $macro['confidence'] : 0,
            strtoupper($macro['risk_level'] ?? 'unavailable'),
        ), 'key');
        $add($report['executive']['macro_summary'] ?? 'AI context is unavailable.', 'body');
        foreach ($macro['analyses'] ?? [] as $analysis) {
            $add(sprintf(
                '%s: %s gold · %s USD · %d%% confidence',
                $analysis['provider'] ?? 'AI provider',
                strtoupper($analysis['gold_bias'] ?? 'unavailable'),
                strtoupper($analysis['usd_strength'] ?? 'unavailable'),
                is_numeric($analysis['confidence'] ?? null) ? (int) $analysis['confidence'] : 0,
            ), 'bullet');
        }
        $add('AI context is independent and never changes the deterministic technical score. Generated '.$this->time($macro['generated_at'] ?? null).'.', 'meta');

        $events = $report['executive']['events'] ?? [];
        if ($events !== []) {
            $add('VERIFIED EVENT HIGHLIGHTS', 'heading');
            foreach ($events as $event) {
                $add(($event['headline'] ?? 'Event').' · '.strtoupper($event['direction'] ?? 'mixed'), 'key');
                $add($event['why_it_matters'] ?? '', 'body');
                $add('Source: '.($event['source_name'] ?? 'Unavailable').' · '.($event['source_url'] ?? 'Unavailable'), 'source');
            }
        }

        $history = $report['history'] ?? [];
        $summary = $history['summary'] ?? [];
        $add('RELIABILITY SNAPSHOT', 'heading');
        $add(sprintf(
            '7-day window · %s mature · %s pending · %s alignment · %s sample quality · %s flips',
            $summary['mature_samples'] ?? 0,
            $summary['pending_samples'] ?? 0,
            isset($summary['alignment_percent']) ? $summary['alignment_percent'].'%' : 'collecting',
            strtoupper($summary['sample_quality'] ?? 'insufficient'),
            $summary['flip_count'] ?? 0,
        ), 'key');
        $add($history['availability']['message'] ?? 'Historical evidence is unavailable.', 'body');
        $add($history['methodology']['statement'] ?? 'Historical alignment is not a profitability backtest.', 'meta');

        $add('BOUNDARIES & RISK', 'heading');
        foreach ($report['boundaries'] ?? [] as $boundary) {
            $add($boundary, 'bullet');
        }
        $add($report['disclaimer'], 'notice');

        return $blocks;
    }

    private function score(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        $number = (int) $value;

        return ($number > 0 ? '+' : '').$number;
    }

    private function number(mixed $value, int $decimals = 2): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals, '.', ',') : '—';
    }

    private function time(mixed $value): string
    {
        return $value ? CarbonImmutable::parse((string) $value)->utc()->format('M j, Y H:i').' UTC' : 'Unavailable';
    }
}
