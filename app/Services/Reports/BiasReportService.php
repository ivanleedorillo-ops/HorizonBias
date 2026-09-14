<?php

namespace App\Services\Reports;

use App\Services\Dashboard\DashboardService;
use App\Services\History\ReliabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

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
        $timeframes = array_values($dashboard['timeframes'] ?? []);
        $fingerprint = hash('sha256', json_encode([
            $dashboard['mode'] ?? 'unavailable',
            $dashboard['symbol'] ?? 'XAU/USD',
            $completedAt,
            $dashboard['overall']['generated_at'] ?? null,
            $dashboard['overall']['score'] ?? null,
            $dashboard['macro']['generated_at'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return [
            'schema_version' => 2,
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
            'timeframes' => $timeframes,
            'macro' => $macro,
            'executive' => $this->executiveSummary($dashboard, $timeframes, $macro),
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

    private function executiveSummary(array $dashboard, array $timeframes, array $macro): array
    {
        $valid = collect($timeframes)->filter(fn (array $frame): bool => is_numeric($frame['score'] ?? null));
        $bullish = $valid->filter(fn (array $frame): bool => (int) $frame['score'] >= 20)->count();
        $bearish = $valid->filter(fn (array $frame): bool => (int) $frame['score'] <= -20)->count();
        $neutral = $valid->count() - $bullish - $bearish;
        $strongest = $valid->sortByDesc('score')->first();
        $weakest = $valid->sortBy('score')->first();
        $overall = $dashboard['overall'] ?? [];
        $overallLabel = (string) ($overall['label'] ?? 'Unavailable');
        $overallScore = $this->score($overall['score'] ?? null);
        $goldBias = strtoupper((string) ($macro['gold_bias'] ?? 'unavailable'));
        $usdStrength = strtoupper((string) ($macro['usd_strength'] ?? 'unavailable'));
        $confidence = is_numeric($macro['confidence'] ?? null) ? (int) $macro['confidence'] : 0;

        $takeaways = [];
        $takeaways[] = sprintf(
            '%s technical bias (%s), with %d bullish, %d bearish, and %d neutral horizon%s.',
            $overallLabel,
            $overallScore,
            $bullish,
            $bearish,
            $neutral,
            $valid->count() === 1 ? '' : 's',
        );

        if ($strongest && $weakest) {
            $takeaways[] = sprintf(
                'Range: %s is strongest at %s; %s is weakest at %s.',
                $strongest['label'] ?? $strongest['key'] ?? 'Unknown',
                $this->score($strongest['score']),
                $weakest['label'] ?? $weakest['key'] ?? 'Unknown',
                $this->score($weakest['score']),
            );
        }

        $takeaways[] = sprintf(
            'AI context: %s gold, %s USD, %d%% confidence; it does not change the technical score.',
            $goldBias,
            $usdStrength,
            $confidence,
        );

        if ($goldBias === 'BEARISH' && $usdStrength === 'WEAK') {
            $takeaways[] = 'Weak USD is normally supportive for gold, but the stored bearish evidence currently outweighs that opposing factor.';
        } elseif ($goldBias === 'BULLISH' && $usdStrength === 'STRONG') {
            $takeaways[] = 'Strong USD is normally a headwind for gold, but the stored bullish evidence currently outweighs that opposing factor.';
        }

        return [
            'headline' => $overallLabel.' technical bias · '.$goldBias.' AI gold context',
            'takeaways' => array_slice($takeaways, 0, 4),
            'macro_summary' => $this->concise((string) ($macro['summary'] ?? 'AI context is unavailable.'), 360),
            'events' => array_map(function (array $event): array {
                $event['why_it_matters'] = $this->concise((string) ($event['why_it_matters'] ?? ''), 220);

                return $event;
            }, array_slice($macro['events'] ?? [], 0, 3)),
        ];
    }

    private function concise(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        $sentence = preg_split('/(?<=[.!?])\s+/u', $text, 2)[0] ?? $text;
        if (mb_strlen($sentence) >= 80 && mb_strlen($sentence) <= $limit) {
            return $sentence;
        }

        return Str::limit($text, $limit, '…');
    }

    private function score(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        $value = (int) $value;

        return ($value > 0 ? '+' : '').$value;
    }
}
