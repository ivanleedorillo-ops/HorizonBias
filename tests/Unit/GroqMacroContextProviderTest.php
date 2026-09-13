<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\Macro\GroqMacroContextProvider;
use App\Services\Macro\MacroAnalysisSchema;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroqMacroContextProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'horizon.ai.free_tier_only' => true,
            'horizon.groq.api_key' => 'secret-groq-key',
            'horizon.groq.model' => 'openai/gpt-oss-120b',
            'horizon.groq.base_url' => 'https://api.groq.test/openai/v1',
            'horizon.groq.free_tier_models' => ['openai/gpt-oss-120b'],
        ]);
    }

    #[Test]
    public function it_requests_strict_json_schema_from_groq(): void
    {
        Http::fake(['api.groq.test/*' => Http::response($this->response($this->validResult()))]);

        $result = $this->provider()->generate([], []);

        $this->assertSame('Groq GPT-OSS', $result['provider']);
        $this->assertSame('openai/gpt-oss-120b', $result['model']);
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secret-groq-key')
                && data_get($request->data(), 'response_format.type') === 'json_schema'
                && data_get($request->data(), 'response_format.json_schema.strict') === true;
        });
    }

    #[Test]
    public function it_rejects_invalid_json(): void
    {
        Http::fake(['api.groq.test/*' => Http::response(['choices' => [['message' => ['content' => 'not-json']]]])]);

        $this->expectException(AiProviderException::class);
        $this->provider()->generate([], []);
    }

    #[Test]
    public function it_classifies_free_tier_rate_limits(): void
    {
        Http::fake(['api.groq.test/*' => Http::response([], 429)]);

        try {
            $this->provider()->generate([], []);
            $this->fail('Expected a rate-limit failure.');
        } catch (AiProviderException $exception) {
            $this->assertSame('quota_limited', $exception->category);
            $this->assertStringNotContainsString('secret-groq-key', $exception->getMessage());
        }
    }

    #[Test]
    public function it_refuses_a_non_allowlisted_model_before_making_a_request(): void
    {
        config(['horizon.groq.model' => 'unapproved/model']);

        $this->expectException(AiProviderException::class);
        $this->provider()->generate([], []);
    }

    private function provider(): GroqMacroContextProvider
    {
        return new GroqMacroContextProvider(new MacroAnalysisSchema);
    }

    private function validResult(): array
    {
        return [
            'gold_bias' => 'bullish',
            'usd_strength' => 'weak',
            'risk_level' => 'medium',
            'confidence' => 68,
            'summary' => 'The supplied evidence leans supportive for gold.',
            'supporting_factors' => ['USD conditions appear softer.'],
            'opposing_factors' => [],
            'risk_factors' => ['Conditions can reverse.'],
            'historical_assessment' => [
                'sample_quality' => 'insufficient',
                'alignment_trend' => 'unclear',
                'regime_fit' => 'unclear',
                'summary' => 'The historical sample is not mature enough.',
                'caveats' => ['Do not infer reliability from a small sample.'],
            ],
            'citations' => [],
        ];
    }

    private function response(array $result): array
    {
        return ['choices' => [['message' => ['content' => json_encode($result)]]]];
    }
}
