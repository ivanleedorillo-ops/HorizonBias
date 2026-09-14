<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

final class ReportDocumentContent
{
    public function blocks(array $report): array
    {
        $blocks = [];
        $heading = function (string $text) use (&$blocks): void {
            $blocks[] = ['text' => $text, 'style' => 'heading'];
        };
        $line = function (?string $text = '') use (&$blocks): void {
            $blocks[] = ['text' => $text ?? '', 'style' => 'body'];
        };

        $heading($report['title']);
        $line("Report ID: {$report['report_id']}");
        $line('Generated: '.$this->time($report['generated_at']));
        $line('Mode: '.strtoupper($report['mode']));
        if (! empty($report['notice'])) {
            $line('Notice: '.$report['notice']);
        }

        $heading('MARKET OVERVIEW');
        $line('Symbol: '.$report['symbol']);
        $line('Provider: '.($report['system']['market_provider_label'] ?? $report['system']['market_provider'] ?? 'Unavailable'));
        $line('Price: '.$this->number($report['quote']['price'] ?? null, 2).' '.($report['quote']['currency'] ?? 'USD'));
        $line('Technical data completed through: '.$this->time($report['quote']['completed_at'] ?? $report['quote']['as_of'] ?? null));
        $line('Overall bias: '.($report['overall']['label'] ?? 'Unavailable').' ('.$this->score($report['overall']['score'] ?? null).')');
        $line('Overall status: '.(! empty($report['overall']['stale']) ? 'STALE' : ($report['overall'] ? 'READY' : 'UNAVAILABLE')));
        if (! empty($report['overall']['summary'])) {
            $line($report['overall']['summary']);
        }

        $heading('TIMEFRAME EVIDENCE');
        if ($report['timeframes'] === []) {
            $line('No timeframe snapshots are available.');
        }
        foreach ($report['timeframes'] as $timeframe) {
            $components = $timeframe['component_scores'] ?? [];
            $metrics = $timeframe['metrics'] ?? [];
            $line(sprintf(
                '%s | %s (%s) | %s | completed %s',
                $timeframe['label'] ?? $timeframe['key'] ?? 'Unknown timeframe',
                $timeframe['bias'] ?? 'Unavailable',
                $this->score($timeframe['score'] ?? null),
                strtoupper($timeframe['status'] ?? 'unavailable'),
                $this->time($timeframe['completed_at'] ?? $timeframe['data_as_of'] ?? null),
            ));
            $line(sprintf(
                'Components — Trend %s; Momentum %s; Structure %s; Breakout %s',
                $this->score($components['trend'] ?? null),
                $this->score($components['momentum'] ?? null),
                $this->score($components['structure'] ?? null),
                $this->score($components['breakout'] ?? null),
            ));
            $line(sprintf(
                'Indicators — Close %s; EMA20 %s; EMA50 %s; EMA200 %s; RSI14 %s; MACD %s; Signal %s; ROC10 %s; ATR14 %s; ADX14 %s',
                $this->number($metrics['close'] ?? null),
                $this->number($metrics['ema20'] ?? null),
                $this->number($metrics['ema50'] ?? null),
                $this->number($metrics['ema200'] ?? null),
                $this->number($metrics['rsi14'] ?? null),
                $this->number($metrics['macd'] ?? null),
                $this->number($metrics['macd_signal'] ?? null),
                $this->number($metrics['roc10'] ?? null),
                $this->number($metrics['atr14'] ?? null),
                $this->number($metrics['adx14'] ?? null),
            ));
            foreach (array_slice($timeframe['explanations'] ?? [], 0, 5) as $explanation) {
                $line('• '.$explanation);
            }
            $line();
        }

        $macro = $report['macro'];
        $heading('AI MACRO CONTEXT — SEPARATE FROM TECHNICAL SCORING');
        $line(sprintf(
            'Status: %s | Gold bias: %s | USD strength: %s | Agreement: %s | Confidence: %s%% | Risk: %s',
            strtoupper($macro['status'] ?? 'unavailable'),
            strtoupper($macro['gold_bias'] ?? 'unavailable'),
            strtoupper($macro['usd_strength'] ?? 'unavailable'),
            strtoupper(str_replace('_', ' ', $macro['agreement'] ?? 'unavailable')),
            is_numeric($macro['confidence'] ?? null) ? (int) $macro['confidence'] : 0,
            strtoupper($macro['risk_level'] ?? 'unavailable'),
        ));
        $line('Generated: '.$this->time($macro['generated_at'] ?? null));
        $line($macro['summary'] ?? 'AI context is unavailable.');
        foreach ($macro['limitations'] ?? [] as $limitation) {
            $line('Limitation: '.$limitation);
        }
        foreach ($macro['analyses'] ?? [] as $analysis) {
            $line(sprintf(
                '%s (%s): %s gold, %s USD, %s%% confidence. %s',
                $analysis['provider'] ?? 'AI provider',
                $analysis['model'] ?? 'model unavailable',
                strtoupper($analysis['gold_bias'] ?? 'unavailable'),
                strtoupper($analysis['usd_strength'] ?? 'unavailable'),
                is_numeric($analysis['confidence'] ?? null) ? (int) $analysis['confidence'] : 0,
                $analysis['summary'] ?? '',
            ));
        }

        $heading('VERIFIED EVENT CONTEXT');
        if (($macro['events'] ?? []) === []) {
            $line('No verified event citations are available in this stored assessment.');
        }
        foreach ($macro['events'] ?? [] as $event) {
            $line(($event['headline'] ?? 'Event').' ['.strtoupper($event['direction'] ?? 'mixed').']');
            $line($event['why_it_matters'] ?? '');
            $line('Source: '.($event['source_name'] ?? 'Source unavailable').' — '.($event['source_url'] ?? 'URL unavailable'));
        }

        $history = $report['history'];
        $summary = $history['summary'] ?? [];
        $heading('BIAS HISTORY AND HISTORICAL ALIGNMENT');
        $line($history['availability']['message'] ?? 'Historical evidence is unavailable.');
        $line(sprintf(
            'Window: %s | Mature samples: %s | Pending: %s | Alignment: %s | Sample quality: %s | Bias flips: %s',
            $history['range'] ?? '7d',
            $summary['mature_samples'] ?? 0,
            $summary['pending_samples'] ?? 0,
            isset($summary['alignment_percent']) ? $summary['alignment_percent'].'%' : 'Collecting',
            strtoupper($summary['sample_quality'] ?? 'insufficient'),
            $summary['flip_count'] ?? 0,
        ));
        $line($history['methodology']['statement'] ?? 'Historical alignment is not a profitability backtest.');
        foreach ($history['changes'] ?? [] as $change) {
            $line('Recent change: '.($change['headline'] ?? ''));
        }

        $heading('BOUNDARIES AND RISK NOTICE');
        foreach ($report['boundaries'] as $boundary) {
            $line($boundary);
        }
        $line($report['disclaimer']);

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
