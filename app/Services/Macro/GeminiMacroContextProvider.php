<?php

namespace App\Services\Macro;

use App\Contracts\MacroContextProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiMacroContextProvider implements MacroContextProvider
{
    public function name(): string
    {
        return 'Gemini';
    }

    public function generate(array $technicalContext): array
    {
        $apiKey = config('horizon.gemini.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'stance' => ['type' => 'string', 'enum' => ['bullish', 'bearish', 'mixed']],
                'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'summary' => ['type' => 'string'],
                'events' => ['type' => 'array', 'maxItems' => 6, 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'headline' => ['type' => 'string'],
                        'why_it_matters' => ['type' => 'string'],
                        'direction' => ['type' => 'string', 'enum' => ['bullish', 'bearish', 'mixed']],
                        'published_at' => ['type' => ['string', 'null']],
                        'source_name' => ['type' => 'string'],
                        'source_url' => ['type' => 'string'],
                    ],
                    'required' => ['headline', 'why_it_matters', 'direction', 'published_at', 'source_name', 'source_url'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['stance', 'risk_level', 'summary', 'events'],
            'additionalProperties' => false,
        ];

        $body = [
            'model' => config('horizon.gemini.model'),
            'input' => $this->prompt($technicalContext),
            'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema],
        ];
        if (config('horizon.gemini.search_grounding')) {
            $body['tools'] = [['type' => 'google_search']];
        }

        try {
            $response = Http::baseUrl(config('horizon.gemini.base_url'))
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()->asJson()->timeout(30)->connectTimeout(5)
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->post('/interactions', $body);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Gemini connection failed.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Gemini returned HTTP {$response->status()}.");
        }
        $payload = $response->json();
        $text = is_array($payload) ? $this->extractOutputText($payload) : null;
        if (! is_string($text)) {
            throw new RuntimeException('Gemini response did not contain structured output.');
        }
        $result = json_decode($text, true);
        if (! is_array($result)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $this->validate($result);
    }

    private function extractOutputText(array $payload): ?string
    {
        // output_text is an SDK convenience property and is not normally present
        // in raw REST responses. Retain support for it for compatible gateways.
        if (is_string($payload['output_text'] ?? null)) {
            return $payload['output_text'];
        }

        $steps = $payload['steps'] ?? null;
        if (! is_array($steps)) {
            return null;
        }

        foreach (array_reverse($steps) as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'model_output' || ! is_array($step['content'] ?? null)) {
                continue;
            }

            $parts = [];
            foreach ($step['content'] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }

            if ($parts !== []) {
                return implode('', $parts);
            }
        }

        return null;
    }

    public function validate(array $result): array
    {
        if (! in_array($result['stance'] ?? null, ['bullish', 'bearish', 'mixed'], true)
            || ! in_array($result['risk_level'] ?? null, ['low', 'medium', 'high'], true)
            || ! is_string($result['summary'] ?? null)
            || trim($result['summary']) === ''
            || mb_strlen($result['summary']) > 2000
            || ! is_array($result['events'] ?? null)
            || count($result['events']) > 6) {
            throw new RuntimeException('Gemini output failed semantic validation.');
        }

        foreach ($result['events'] as $event) {
            if (! is_array($event)
                || ! in_array($event['direction'] ?? null, ['bullish', 'bearish', 'mixed'], true)
                || ! is_string($event['headline'] ?? null) || trim($event['headline']) === ''
                || ! is_string($event['why_it_matters'] ?? null) || trim($event['why_it_matters']) === ''
                || ! is_string($event['source_name'] ?? null) || trim($event['source_name']) === ''
                || ! $this->validUrl($event['source_url'] ?? null)) {
                throw new RuntimeException('Gemini event failed citation validation.');
            }
            if (($event['published_at'] ?? null) !== null && strtotime($event['published_at']) === false) {
                throw new RuntimeException('Gemini event has an invalid publication timestamp.');
            }
        }

        return $result;
    }

    private function validUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function prompt(array $technicalContext): string
    {
        return 'Create a concise, source-grounded macro and world-event brief relevant only to XAU/USD. '
            .'Consider central banks, inflation, employment, USD, Treasury/real yields, geopolitics, systemic risk sentiment, and official gold demand. '
            .'Prefer verified events from the last 24 hours. Never invent facts, quotes, dates, or sources. Every event must have a direct source URL. '
            .'If current evidence is insufficient, use mixed stance and explicitly say so. This is context, not financial advice, and must not override the technical bias. '
            .'Current deterministic technical snapshots (context only): '.json_encode($technicalContext, JSON_THROW_ON_ERROR);
    }
}
