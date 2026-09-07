<?php

namespace Tests\Unit;

use App\Services\Macro\GeminiMacroContextProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GeminiMacroContextProviderTest extends TestCase
{
    private array $valid = [
        'stance' => 'mixed', 'risk_level' => 'medium', 'summary' => 'Verified forces are currently mixed.',
        'events' => [['headline' => 'Policy update', 'why_it_matters' => 'Rates affect the opportunity cost of gold.', 'direction' => 'mixed', 'published_at' => '2026-09-01T12:00:00Z', 'source_name' => 'Official source', 'source_url' => 'https://example.com/event']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['horizon.gemini.api_key' => 'secret-test-key', 'horizon.gemini.search_grounding' => false]);
    }

    #[Test] public function it_requests_and_validates_structured_output(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'completed',
            'steps' => [[
                'type' => 'model_output',
                'content' => [['type' => 'text', 'text' => json_encode($this->valid)]],
            ]],
        ])]);
        $result = (new GeminiMacroContextProvider)->generate([]);
        $this->assertSame('mixed', $result['stance']);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'secret-test-key') && ! array_key_exists('tools', $request->data()));
    }

    #[Test] public function it_uses_the_last_model_output_step_from_a_rest_response(): void
    {
        Http::fake(['*' => Http::response([
            'steps' => [
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => '{"stance":"mixed"}']]],
                ['type' => 'tool_result', 'content' => []],
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($this->valid)]]],
            ],
        ])]);

        $result = (new GeminiMacroContextProvider)->generate([]);

        $this->assertSame('Verified forces are currently mixed.', $result['summary']);
    }

    #[Test] public function it_rejects_invalid_json_wrong_enums_and_unsafe_urls(): void
    {
        $provider = new GeminiMacroContextProvider;
        foreach ([
            ['stance' => 'certain', 'risk_level' => 'medium', 'summary' => 'x', 'events' => []],
            [...$this->valid, 'events' => [[...$this->valid['events'][0], 'source_url' => 'javascript:alert(1)']]],
            [...$this->valid, 'events' => [[...$this->valid['events'][0], 'source_url' => '']]],
        ] as $invalid) {
            try { $provider->validate($invalid); $this->fail('Invalid output should throw.'); } catch (RuntimeException) { $this->assertTrue(true); }
        }
    }

    #[Test] public function it_rejects_a_response_without_structured_json(): void
    {
        Http::fake(['*' => Http::response(['output_text' => 'not-json'])]);
        $this->expectException(RuntimeException::class);
        (new GeminiMacroContextProvider)->generate([]);
    }

    #[Test] public function it_rejects_http_failures_without_exposing_the_key(): void
    {
        Http::fake(['*' => Http::response([], 429)]);
        try { (new GeminiMacroContextProvider)->generate([]); $this->fail(); } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('secret-test-key', $exception->getMessage());
        }
    }
}
