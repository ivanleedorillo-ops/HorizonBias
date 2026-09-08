<?php

namespace App\Services\Macro;

use App\Contracts\MacroContextProvider;
use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class GeminiMacroContextProvider implements MacroContextProvider
{
    public function __construct(private readonly MacroAnalysisSchema $analysisSchema) {}

    public function name(): string
    {
        return 'Gemini';
    }

    public function model(): string
    {
        return (string) config('horizon.gemini.model');
    }

    public function configured(): bool
    {
        return is_string(config('horizon.gemini.api_key')) && config('horizon.gemini.api_key') !== '';
    }

    public function generate(array $technicalContext, array $evidence): array
    {
        $apiKey = config('horizon.gemini.api_key');
        if (! $this->configured()) {
            throw new AiProviderException($this->name(), 'unavailable', 'Gemini API key is not configured.');
        }

        $this->ensureFreeTierModel();
        $body = [
            'model' => $this->model(),
            'input' => $this->analysisSchema->prompt($technicalContext, $evidence),
            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $this->analysisSchema->jsonSchema($evidence),
            ],
        ];

        try {
            $response = Http::baseUrl(config('horizon.gemini.base_url'))
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()->asJson()->timeout(30)->connectTimeout(5)
                ->post('/interactions', $body);
        } catch (ConnectionException $exception) {
            throw new AiProviderException($this->name(), 'connection', 'Gemini connection failed.', $exception);
        }

        $this->assertSuccessful($response->status(), $response->successful());
        $payload = $response->json();
        $text = is_array($payload) ? $this->extractOutputText($payload) : null;
        if (! is_string($text)) {
            throw new AiProviderException($this->name(), 'invalid_response', 'Gemini response did not contain structured output.');
        }

        $result = json_decode($text, true);
        if (! is_array($result)) {
            throw new AiProviderException($this->name(), 'invalid_response', 'Gemini returned invalid JSON.');
        }

        try {
            return $this->analysisSchema->validate($result, $evidence, $this->name(), $this->model());
        } catch (\RuntimeException $exception) {
            throw new AiProviderException($this->name(), 'invalid_response', $exception->getMessage(), $exception);
        }
    }

    private function extractOutputText(array $payload): ?string
    {
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

    private function assertSuccessful(int $status, bool $successful): void
    {
        if ($successful) {
            return;
        }

        $category = match (true) {
            in_array($status, [401, 403], true) => 'credentials',
            $status === 429 => 'quota_limited',
            $status >= 500 => 'provider_error',
            default => 'request_error',
        };

        throw new AiProviderException($this->name(), $category, "Gemini returned HTTP {$status}.");
    }

    private function ensureFreeTierModel(): void
    {
        if (! config('horizon.ai.free_tier_only')) {
            return;
        }

        if (! in_array($this->model(), config('horizon.gemini.free_tier_models', []), true)) {
            throw new AiProviderException($this->name(), 'configuration', 'Configured Gemini model is not in the HorizonBias free-tier allowlist.');
        }
    }
}
