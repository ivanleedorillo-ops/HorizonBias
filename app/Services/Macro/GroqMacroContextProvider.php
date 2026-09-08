<?php

namespace App\Services\Macro;

use App\Contracts\MacroContextProvider;
use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class GroqMacroContextProvider implements MacroContextProvider
{
    public function __construct(private readonly MacroAnalysisSchema $analysisSchema) {}

    public function name(): string
    {
        return 'Groq GPT-OSS';
    }

    public function model(): string
    {
        return (string) config('horizon.groq.model');
    }

    public function configured(): bool
    {
        return is_string(config('horizon.groq.api_key')) && config('horizon.groq.api_key') !== '';
    }

    public function generate(array $technicalContext, array $evidence): array
    {
        $apiKey = config('horizon.groq.api_key');
        if (! $this->configured()) {
            throw new AiProviderException($this->name(), 'unavailable', 'Groq API key is not configured.');
        }

        $this->ensureFreeTierModel();
        $body = [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are the independent GPT-OSS analyst in HorizonBias. Follow the supplied evidence boundary and return JSON only.',
                ],
                ['role' => 'user', 'content' => $this->analysisSchema->prompt($technicalContext, $evidence)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'horizon_bias_analysis',
                    'strict' => true,
                    'schema' => $this->analysisSchema->jsonSchema($evidence),
                ],
            ],
        ];

        try {
            $response = Http::baseUrl(config('horizon.groq.base_url'))
                ->withToken($apiKey)
                ->acceptJson()->asJson()->timeout(30)->connectTimeout(5)
                ->post('/chat/completions', $body);
        } catch (ConnectionException $exception) {
            throw new AiProviderException($this->name(), 'connection', 'Groq connection failed.', $exception);
        }

        $this->assertSuccessful($response->status(), $response->successful());
        $payload = $response->json();
        $text = is_array($payload) ? data_get($payload, 'choices.0.message.content') : null;
        if (! is_string($text)) {
            throw new AiProviderException($this->name(), 'invalid_response', 'Groq response did not contain structured output.');
        }

        $result = json_decode($text, true);
        if (! is_array($result)) {
            throw new AiProviderException($this->name(), 'invalid_response', 'Groq returned invalid JSON.');
        }

        try {
            return $this->analysisSchema->validate($result, $evidence, $this->name(), $this->model());
        } catch (\RuntimeException $exception) {
            throw new AiProviderException($this->name(), 'invalid_response', $exception->getMessage(), $exception);
        }
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

        throw new AiProviderException($this->name(), $category, "Groq returned HTTP {$status}.");
    }

    private function ensureFreeTierModel(): void
    {
        if (! config('horizon.ai.free_tier_only')) {
            return;
        }

        if (! in_array($this->model(), config('horizon.groq.free_tier_models', []), true)) {
            throw new AiProviderException($this->name(), 'configuration', 'Configured Groq model is not in the HorizonBias free-tier allowlist.');
        }
    }
}
