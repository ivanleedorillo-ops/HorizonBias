<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\Macro\GeminiMacroContextProvider;
use App\Services\Macro\MacroAnalysisSchema;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeminiMacroContextProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'horizon.ai.free_tier_only' => true,
            'horizon.gemini.api_key' => 'secret-test-key',
            'horizon.gemini.model' => 'gemini-3.5-flash-lite',
            'horizon.gemini.free_tier_models' => ['gemini-3.5-flash-lite'],
        ]);
    }

    #[Test]
    public function it_requests_strict_structured_analysis_without_search_tools(): void
    {
        Http::fake(['*/interactions' => Http::response($this->interactionResponse($this->validResult()))]);

        $result = $this->provider()->generate([], []);

        $this->assertSame('neutral', $result['gold_bias']);
        $this->assertSame('Gemini', $result['provider']);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->hasHeader('x-goog-api-key', 'secret-test-key')
                && ! array_key_exists('tools', $data)
                && data_get($data, 'response_format.type') === 'text'
                && data_get($data, 'response_format.schema.properties.citations.maxItems') === 0;
        });
    }

    #[Test]
    public function it_hydrates_only_server_owned_citations(): void
    {
        $result = $this->validResult();
        $result['citations'] = [[
            'citation_id' => 'S1',
            'why_it_matters' => 'Policy-rate expectations affect real yields and gold carrying costs.',
            'direction' => 'mixed',
        ]];
        Http::fake(['*/interactions' => Http::response($this->interactionResponse($result))]);

        $analysis = $this->provider()->generate([], $this->evidence());

        $this->assertSame('Federal Reserve issues FOMC statement', $analysis['events'][0]['headline']);
        $this->assertSame('https://www.federalreserve.gov/example.htm', $analysis['events'][0]['source_url']);
    }

    #[Test]
    public function it_rejects_unknown_citations_and_invalid_enums(): void
    {
        $result = $this->validResult();
        $result['gold_bias'] = 'certain';
        Http::fake(['*/interactions' => Http::response($this->interactionResponse($result))]);

        $this->expectException(AiProviderException::class);
        $this->provider()->generate([], $this->evidence());
    }

    #[Test]
    public function it_rejects_an_invalid_historical_assessment(): void
    {
        $result = $this->validResult();
        $result['historical_assessment']['sample_quality'] = 'guaranteed';
        Http::fake(['*/interactions' => Http::response($this->interactionResponse($result))]);

        $this->expectException(AiProviderException::class);
        $this->provider()->generate([], []);
    }

    #[Test]
    public function it_classifies_quota_failures_without_exposing_the_key(): void
    {
        Http::fake(['*/interactions' => Http::response([], 429)]);

        try {
            $this->provider()->generate([], []);
            $this->fail('Expected a quota failure.');
        } catch (AiProviderException $exception) {
            $this->assertSame('quota_limited', $exception->category);
            $this->assertStringNotContainsString('secret-test-key', $exception->getMessage());
        }
    }

    #[Test]
    public function it_refuses_a_model_outside_the_free_tier_allowlist(): void
    {
        config(['horizon.gemini.model' => 'paid-or-unapproved-model']);

        $this->expectException(AiProviderException::class);
        $this->provider()->generate([], []);
        Http::assertNothingSent();
    }

    private function provider(): GeminiMacroContextProvider
    {
        return new GeminiMacroContextProvider(new MacroAnalysisSchema);
    }

    private function validResult(): array
    {
        return [
            'gold_bias' => 'neutral',
            'usd_strength' => 'neutral',
            'risk_level' => 'medium',
            'confidence' => 55,
            'summary' => 'Technical and macro evidence is mixed.',
            'supporting_factors' => ['Longer-horizon structure remains constructive.'],
            'opposing_factors' => ['Dollar conditions may constrain gold.'],
            'risk_factors' => ['Evidence can become stale.'],
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

    private function evidence(): array
    {
        return ['S1' => [
            'citation_id' => 'S1',
            'headline' => 'Federal Reserve issues FOMC statement',
            'source_name' => 'Federal Reserve — Monetary Policy',
            'source_url' => 'https://www.federalreserve.gov/example.htm',
            'published_at' => now('UTC')->toIso8601String(),
            'source_summary' => 'The Committee published its policy statement.',
        ]];
    }

    private function interactionResponse(array $result): array
    {
        return [
            'status' => 'completed',
            'steps' => [[
                'type' => 'model_output',
                'content' => [['type' => 'text', 'text' => json_encode($result)]],
            ]],
        ];
    }
}
