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
                $response = Http::withHeaders([
                    'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9',
                    'User-Agent' => 'HorizonBias/1.0 (+local decision-support dashboard)',
                ])->timeout(15)->connectTimeout(5)
                    ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                    ->get($source['url']);

                if (! $response->successful()) {
                    throw new \RuntimeException("Feed returned HTTP {$response->status()}.");
                }

                $items = [...$items, ...$this->parse($response->body(), $source)];
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

    private function parse(string $body, array $source): array
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

    private function plainText(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $limit);
    }

    private function isRelevant(string $value): bool
    {
        return preg_match('/\b(federal reserve|fed|fomc|monetary|policy|inflation|prices?|employment|payroll|unemployment|interest|rates?|yields?|dollar|economic outlook|gdp|personal income|personal consumption|trade|financial stability)\b/i', $value) === 1;
    }
}
