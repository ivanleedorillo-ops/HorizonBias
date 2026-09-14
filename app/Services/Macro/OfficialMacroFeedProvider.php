<?php

namespace App\Services\Macro;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OfficialMacroFeedProvider
{
    public function collect(): array
    {
        $items = [];

        foreach (config('horizon.macro_feeds.sources', []) as $source) {
            try {
                $format = (string) ($source['format'] ?? 'rss');
                $url = str_replace('{year}', now('UTC')->format('Y'), (string) $source['url']);
                $request = Http::withHeaders([
                    'Accept' => in_array($format, ['treasury_json', 'bls_api_json'], true)
                        ? 'application/json'
                        : 'application/rss+xml, application/xml, text/xml;q=0.9',
                    'User-Agent' => 'HorizonBias/1.0 (+local decision-support dashboard)',
                ])->timeout($format === 'bls_api_json' ? 45 : 15)->connectTimeout(5)
                    ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false);

                $response = $format === 'bls_api_json'
                    ? $request->post($url, [
                        'seriesid' => array_keys($source['series'] ?? []),
                        'startyear' => now('UTC')->subYear()->format('Y'),
                        'endyear' => now('UTC')->format('Y'),
                    ])
                    : $request->get($url);

                if (! $response->successful()) {
                    throw new \RuntimeException("Feed returned HTTP {$response->status()}.");
                }

                $parsed = match ($format) {
                    'treasury_json' => $this->parseTreasuryJson($response->body(), $source),
                    'bls_api_json' => $this->parseBlsJson($response->body(), $source),
                    default => $this->parseRss($response->body(), $source),
                };
                $items = [...$items, ...$parsed];
            } catch (\Throwable $exception) {
                Log::warning('Official macro feed refresh failed.', [
                    'source' => $source['name'] ?? 'Unknown source',
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        usort($items, fn (array $a, array $b) => strcmp($b['published_at'], $a['published_at']));

        $catalogue = [];
        foreach ($items as $item) {
            if (collect($catalogue)->contains('source_url', $item['source_url'])) {
                continue;
            }

            $item['citation_id'] = 'S'.(count($catalogue) + 1);
            $catalogue[$item['citation_id']] = $item;

            if (count($catalogue) >= (int) config('horizon.macro_feeds.max_items', 10)) {
                break;
            }
        }

        return $catalogue;
    }

    private function parseRss(string $body, array $source): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException('Feed returned malformed XML.');
        }

        $items = [];
        $maxPerSource = (int) config('horizon.macro_feeds.max_items_per_source', 4);
        foreach ($xml->channel->item as $node) {
            $title = $this->plainText((string) $node->title, 300);
            $summary = $this->plainText((string) $node->description, 800);
            $url = $this->validatedUrl((string) $node->link, $source['allowed_hosts'] ?? []);
            $publishedAt = $this->publishedAt((string) $node->pubDate);

            if ($title === '' || $url === null || $publishedAt === null) {
                continue;
            }
            if (($source['relevance_filter'] ?? true) && ! $this->isRelevant($title.' '.$summary)) {
                continue;
            }

            $items[] = [
                'headline' => $title,
                'source_name' => (string) $source['name'],
                'source_url' => $url,
                'published_at' => $publishedAt->toIso8601String(),
                'source_summary' => $summary,
            ];

            if (count($items) >= $maxPerSource) {
                break;
            }
        }

        return $items;
    }

    private function parseTreasuryJson(string $body, array $source): array
    {
        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('Treasury feed returned malformed JSON.');
        }

        if (! is_array($payload) || ! is_array($payload['items'] ?? null)) {
            throw new \RuntimeException('Treasury feed returned an unexpected response shape.');
        }

        $items = [];
        $maxPerSource = (int) config('horizon.macro_feeds.max_items_per_source', 4);
        foreach ($payload['items'] as $node) {
            if (! is_array($node)) {
                continue;
            }

            $title = $this->plainText((string) ($node['title'] ?? ''), 300);
            $summary = $this->plainText((string) ($node['searchText'] ?? ''), 800);
            $url = $this->absoluteSourceUrl((string) ($node['url'] ?? ''), $source);
            $publishedAt = $this->publishedAt((string) ($node['datetime'] ?? $node['date'] ?? ''));

            if ($title === '' || $url === null || $publishedAt === null) {
                continue;
            }
            if (($source['relevance_filter'] ?? true) && ! $this->isRelevant($title.' '.$summary)) {
                continue;
            }

            $items[] = [
                'headline' => $title,
                'source_name' => (string) $source['name'],
                'source_url' => $url,
                'published_at' => $publishedAt->toIso8601String(),
                'source_summary' => $summary,
            ];

            if (count($items) >= $maxPerSource) {
                break;
            }
        }

        return $items;
    }

    private function parseBlsJson(string $body, array $source): array
    {
        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('BLS API returned malformed JSON.');
        }

        $series = data_get($payload, 'Results.series');
        if (($payload['status'] ?? null) !== 'REQUEST_SUCCEEDED' || ! is_array($series)) {
            throw new \RuntimeException('BLS API returned an unsuccessful or unexpected response.');
        }

        $definitions = $source['series'] ?? [];
        $items = [];
        foreach ($series as $result) {
            if (! is_array($result) || ! is_string($result['seriesID'] ?? null)) {
                continue;
            }

            $definition = $definitions[$result['seriesID']] ?? null;
            if (! is_array($definition)) {
                continue;
            }

            $observations = $this->monthlyBlsObservations($result['data'] ?? []);
            $latest = $observations[0] ?? null;
            if ($latest === null) {
                continue;
            }

            $url = $this->validatedUrl((string) ($definition['source_url'] ?? ''), $source['allowed_hosts'] ?? []);
            if ($url === null) {
                continue;
            }

            $items[] = [
                'headline' => $this->blsHeadline($definition, $latest, $observations[1] ?? null, $observations[12] ?? null),
                'source_name' => (string) $source['name'],
                'source_url' => $url,
                'published_at' => now('UTC')->toIso8601String(),
                'source_summary' => $this->blsSummary($definition, $latest, $observations[1] ?? null, $observations[12] ?? null),
                'timestamp_basis' => 'retrieved_at',
                'observation_period' => $latest['period_name'].' '.$latest['year'],
            ];
        }

        return array_slice($items, 0, (int) config('horizon.macro_feeds.max_items_per_source', 4));
    }

    private function monthlyBlsObservations(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $observations = [];
        foreach ($data as $observation) {
            if (! is_array($observation)
                || ! preg_match('/^M(0[1-9]|1[0-2])$/', (string) ($observation['period'] ?? ''))
                || ! is_numeric($observation['value'] ?? null)
                || ! preg_match('/^\d{4}$/', (string) ($observation['year'] ?? ''))) {
                continue;
            }

            $observations[] = [
                'year' => (int) $observation['year'],
                'period' => (string) $observation['period'],
                'period_name' => $this->plainText((string) ($observation['periodName'] ?? ''), 20),
                'value' => (float) $observation['value'],
            ];
        }

        usort($observations, fn (array $a, array $b) => [$b['year'], $b['period']] <=> [$a['year'], $a['period']]);

        return $observations;
    }

    private function blsHeadline(array $definition, array $latest, ?array $previous, ?array $yearAgo): string
    {
        $label = $this->plainText((string) ($definition['label'] ?? 'BLS economic series'), 120);
        $period = $latest['period_name'].' '.$latest['year'];
        $measure = (string) ($definition['measure'] ?? 'index');

        if ($previous !== null && $measure === 'thousands') {
            $change = $latest['value'] - $previous['value'];

            return sprintf('%s changed by %s thousand in %s', $label, $this->signedNumber($change, 0), $period);
        }
        if ($previous !== null && $measure === 'percent') {
            $change = $latest['value'] - $previous['value'];

            return sprintf('%s was %s%% in %s (%s percentage points monthly)', $label, $this->number($latest['value'], 1), $period, $this->signedNumber($change, 1));
        }
        if ($previous !== null && $measure === 'index' && $previous['value'] != 0.0) {
            $monthly = (($latest['value'] / $previous['value']) - 1) * 100;
            $yearly = $yearAgo !== null && $yearAgo['value'] != 0.0
                ? (($latest['value'] / $yearAgo['value']) - 1) * 100
                : null;

            return sprintf(
                '%s changed %s%% monthly%s in %s',
                $label,
                $this->signedNumber($monthly, 1),
                $yearly === null ? '' : ' and '.$this->signedNumber($yearly, 1).'% yearly',
                $period,
            );
        }

        return sprintf('%s was %s in %s', $label, $this->number($latest['value']), $period);
    }

    private function blsSummary(array $definition, array $latest, ?array $previous, ?array $yearAgo): string
    {
        $measure = (string) ($definition['measure'] ?? 'index');
        $unit = match ($measure) {
            'percent' => '%',
            'thousands' => ' thousand',
            default => ' index points',
        };
        $parts = [
            sprintf('Latest official observation: %s%s for %s %d.', $this->number($latest['value']), $unit, $latest['period_name'], $latest['year']),
        ];
        if ($previous !== null) {
            $parts[] = sprintf('Previous monthly observation: %s%s.', $this->number($previous['value']), $unit);
        }
        if ($yearAgo !== null) {
            $parts[] = sprintf('Same month one year earlier: %s%s.', $this->number($yearAgo['value']), $unit);
        }
        $parts[] = 'Retrieved from the BLS Public Data API; retrieval time is not the original release timestamp.';

        return implode(' ', $parts);
    }

    private function number(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', ',');
    }

    private function signedNumber(float $value, int $decimals): string
    {
        return ($value > 0 ? '+' : '').$this->number($value, $decimals);
    }

    private function publishedAt(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');
        $oldest = $now->subDays((int) config('horizon.macro_feeds.max_age_days', 14));

        return $date->betweenIncluded($oldest, $now->addHours(6)) ? $date : null;
    }

    private function validatedUrl(string $value, array $allowedHosts): ?string
    {
        $url = trim($value);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = collect($allowedHosts)->contains(fn (string $candidate) => $host === strtolower($candidate));

        return $allowed ? $url : null;
    }

    private function absoluteSourceUrl(string $value, array $source): ?string
    {
        $url = trim($value);
        if (str_starts_with($url, '/')) {
            $baseUrl = rtrim((string) ($source['base_url'] ?? ''), '/');
            if ($baseUrl === '') {
                return null;
            }
            $url = $baseUrl.$url;
        }

        return $this->validatedUrl($url, $source['allowed_hosts'] ?? []);
    }

    private function plainText(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $limit);
    }

    private function isRelevant(string $value): bool
    {
        return preg_match('/\b(federal reserve|fed|fomc|monetary|policy|inflation|prices?|cpi|ppi|employment|payroll|unemployment|job openings|wages?|interest|rates?|yields?|real rates?|dollar|currency|foreign exchange|economic outlook|gdp|personal income|personal consumption|trade|financial stability|refunding|borrowing|auction|marketable debt|debt limit|coupon|treasury international capital|portfolio holdings|sanctions?|geopolitic(?:al|s)?|g7|g20|imf|gold|bullion|oil|energy)\b/i', $value) === 1;
    }
}
