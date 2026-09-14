<?php

namespace App\Services\Reports;

use App\Services\Dashboard\DashboardService;
use App\Services\History\ReliabilityService;
use Carbon\CarbonImmutable;

final class BiasReportService
{
    public const DISCLAIMER = 'HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.';

    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ReliabilityService $reliability,
    ) {}

    public function build(): array
    {
        $dashboard = $this->dashboard->data();
        $history = $this->reliability->data('7d', 'overall');
        $generatedAt = CarbonImmutable::now('UTC');
        $completedAt = $dashboard['quote']['completed_at'] ?? $dashboard['quote']['as_of'] ?? null;
        $macro = $dashboard['macro'] ?? [];
        $macro['events'] = $this->safeEvents($macro['events'] ?? []);
        $fingerprint = hash('sha256', json_encode([
            $dashboard['mode'] ?? 'unavailable',
            $dashboard['symbol'] ?? 'XAU/USD',
            $completedAt,
            $dashboard['overall']['generated_at'] ?? null,
            $dashboard['overall']['score'] ?? null,
            $dashboard['macro']['generated_at'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return [
            'schema_version' => 1,
            'report_id' => sprintf(
                'HB-%s-%s',
                $completedAt ? CarbonImmutable::parse($completedAt)->utc()->format('Ymd-His') : $generatedAt->format('Ymd-His'),
                strtoupper(substr($fingerprint, 0, 8)),
            ),
            'title' => 'HorizonBias XAU/USD Bias Report',
            'generated_at' => $generatedAt->toIso8601String(),
            'mode' => $dashboard['mode'] ?? 'unavailable',
            'notice' => $dashboard['notice'] ?? null,
            'symbol' => $dashboard['symbol'] ?? 'XAU/USD',
            'quote' => $dashboard['quote'] ?? [],
            'overall' => $dashboard['overall'] ?? null,
            'timeframes' => array_values($dashboard['timeframes'] ?? []),
            'macro' => $macro,
            'history' => [
                'range' => $history['range'] ?? '7d',
                'scope' => $history['scope'] ?? 'overall',
                'availability' => $history['availability'] ?? ['status' => 'unavailable', 'message' => 'Historical evidence is unavailable.'],
                'summary' => $history['summary'] ?? [],
                'changes' => array_slice($history['changes'] ?? [], 0, 5),
                'methodology' => $history['methodology'] ?? [],
            ],
            'system' => $dashboard['system'] ?? [],
            'boundaries' => [
                'technical_ai_separation' => 'AI context is displayed separately and never alters the deterministic technical score.',
                'tradingview_separation' => 'TradingView display data is not used in HorizonBias calculations or this report.',
                'licensing' => ($dashboard['mode'] ?? null) === 'live'
                    ? 'This report contains stored provider-derived analysis. Confirm external display and redistribution rights before public distribution.'
                    : 'Illustrative demo content is not live market analysis.',
            ],
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    public function filename(array $report, string $extension): string
    {
        $timestamp = CarbonImmutable::parse($report['quote']['completed_at'] ?? $report['generated_at'])->utc()->format('Ymd-His');

        return "horizonbias-xauusd-{$timestamp}-utc.{$extension}";
    }

    private function safeEvents(array $events): array
    {
        return array_values(array_filter($events, function (mixed $event): bool {
            if (! is_array($event) || ! is_string($event['source_url'] ?? null)) {
                return false;
            }

            $url = $event['source_url'];

            return filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
        }));
    }
}
