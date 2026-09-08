<?php

namespace Tests\Unit;

use App\Services\Macro\GeminiMacroContextProvider;
use App\Services\Macro\OfficialMacroFeedProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GeminiMacroContextProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'horizon.gemini.api_key' => 'secret-test-key',
            'horizon.gemini.search_grounding' => false,
            'horizon.macro_feeds.sources' => [],
        ]);
    }

    #[Test]
    public function it_requests_structured_output_without_search_tools(): void
    {
        Http::fake(['*/interactions' => Http::response($this->interactionResponse([
            'stance' => 'mixed',
            'risk_level' => 'medium',
            'summary' => 'Technical evidence is mixed and no official feed items are available.',
            'events' => [],
        ]))]);

        $result = $this->provider()->generate([]);

        $this->assertSame('mixed', $result['stance']);
        $this->assertSame([], $result['events']);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->hasHeader('x-goog-api-key', 'secret-test-key')
                && ! array_key_exists('tools', $data)
                && data_get($data, 'response_format.type') === 'text'
                && data_get($data, 'response_format.schema.properties.events.maxItems') === 0;
        });
    }

    #[Test]
    public function it_hydrates_only_server_owned_official_feed_citations(): void
    {
        $evidence = $this->evidence();
        $response = [
            'stance' => 'mixed',
            'risk_level' => 'medium',
            'summary' => 'Official monetary-policy context is mixed.',
            'events' => [[
                'citation_id' => 'S1',
                'why_it_matters' => 'Policy-rate expectations affect real yields and the opportunity cost of gold.',
                'direction' => 'mixed',
            ]],
        ];

        Http::fake(['*/interactions' => Http::response($this->interactionResponse($response))]);
        $result = $this->provider()->validate($response, $evidence);

        $this->assertSame('Federal Reserve issues FOMC statement', $result['events'][0]['headline']);
        $this->assertSame('Federal Reserve — Monetary Policy', $result['events'][0]['source_name']);
        $this->assertSame('https://www.federalreserve.gov/example.htm', $result['events'][0]['source_url']);
        $this->assertArrayNotHasKey('citation_id', $result['events'][0]);
    }

    #[Test]
    public function it_rejects_unknown_citation_ids_and_invalid_enums(): void
    {
        $base = [
            'stance' => 'mixed',
            'risk_level' => 'medium',
            'summary' => 'Mixed evidence.',
            'events' => [[
                'citation_id' => 'S999',
                'why_it_matters' => 'Unsupported citation.',
                'direction' => 'mixed',
            ]],
        ];

        foreach ([$base, [...$base, 'stance' => 'certain']] as $invalid) {
            try {
                $this->provider()->validate($invalid, $this->evidence());
                $this->fail('Invalid output should throw.');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_uses_the_last_model_output_step(): void
    {
        $valid = [
            'stance' => 'mixed',
            'risk_level' => 'medium',
            'summary' => 'Verified forces are mixed.',
            'events' => [],
        ];
        Http::fake(['*/interactions' => Http::response([
            'steps' => [
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => '{"stance":"mixed"}']]],
                ['type' => 'tool_result', 'content' => []],
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($valid)]]],
            ],
        ])]);

        $result = $this->provider()->generate([]);

        $this->assertSame('Verified forces are mixed.', $result['summary']);
    }

    #[Test]
    public function it_rejects_http_failures_without_exposing_the_key(): void
    {
        Http::fake(['*/interactions' => Http::response([], 429)]);

        try {
            $this->provider()->generate([]);
            $this->fail('Expected provider failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('secret-test-key', $exception->getMessage());
        }
    }

    private function provider(): GeminiMacroContextProvider
    {
        return new GeminiMacroContextProvider(new OfficialMacroFeedProvider);
    }

    private function evidence(): array
    {
        return ['S1' => [
            'citation_id' => 'S1',
            'headline' => 'Federal Reserve issues FOMC statement',
            'source_name' => 'Federal Reserve — Monetary Policy',
            'source_url' => 'https://www.federalreserve.gov/example.htm',
            'published_at' => '2026-09-08T10:00:00+00:00',
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
