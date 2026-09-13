<?php

namespace Tests\Unit;

use App\Services\Macro\AiConsensusEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiConsensusEngineTest extends TestCase
{
    #[Test]
    public function matching_models_produce_an_agreed_direction(): void
    {
        $result = (new AiConsensusEngine)->build([
            'gemini' => $this->analysis('Gemini', 'bullish', 'weak', 80),
            'groq' => $this->analysis('Groq GPT-OSS', 'bullish', 'weak', 76),
        ], $this->technicalContext(), $this->evidence());

        $this->assertSame('agree', $result['agreement']);
        $this->assertSame('bullish', $result['gold_bias']);
        $this->assertSame('weak', $result['usd_strength']);
        $this->assertSame(2, $result['provider_count']);
        $this->assertLessThanOrEqual(78, $result['confidence']);
    }

    #[Test]
    public function opposing_models_produce_neutral_low_confidence_context(): void
    {
        $result = (new AiConsensusEngine)->build([
            'gemini' => $this->analysis('Gemini', 'bullish', 'weak', 90),
            'groq' => $this->analysis('Groq GPT-OSS', 'bearish', 'strong', 90),
        ], $this->technicalContext(), $this->evidence());

        $this->assertSame('disagree', $result['agreement']);
        $this->assertSame('neutral', $result['gold_bias']);
        $this->assertSame('neutral', $result['usd_strength']);
        $this->assertLessThanOrEqual(45, $result['confidence']);
    }

    #[Test]
    public function a_single_model_is_capped_and_disclosed(): void
    {
        $result = (new AiConsensusEngine)->build([
            'gemini' => $this->analysis('Gemini', 'bearish', 'strong', 95),
        ], $this->technicalContext(), $this->evidence());

        $this->assertSame('single_model', $result['agreement']);
        $this->assertSame('bearish', $result['gold_bias']);
        $this->assertLessThanOrEqual(55, $result['confidence']);
        $this->assertContains('Only one AI provider returned a valid assessment.', $result['limitations']);
    }

    #[Test]
    public function duplicate_source_urls_are_merged(): void
    {
        $one = $this->analysis('Gemini', 'neutral', 'neutral', 60);
        $two = $this->analysis('Groq GPT-OSS', 'neutral', 'neutral', 60);
        $event = [
            'citation_id' => 'S1', 'headline' => 'Official release', 'why_it_matters' => 'Relevant context.',
            'direction' => 'mixed', 'published_at' => now('UTC')->toIso8601String(),
            'source_name' => 'Official source', 'source_url' => 'https://example.gov/release',
        ];
        $one['events'] = [$event];
        $two['events'] = [$event];

        $result = (new AiConsensusEngine)->build(['gemini' => $one, 'groq' => $two], $this->technicalContext(), $this->evidence());

        $this->assertCount(1, $result['events']);
        $this->assertArrayNotHasKey('citation_id', $result['events'][0]);
    }

    #[Test]
    public function historical_caveats_are_deduplicated_by_deterministic_consensus(): void
    {
        $one = $this->analysis('Gemini', 'neutral', 'neutral', 60);
        $two = $this->analysis('Groq GPT-OSS', 'neutral', 'neutral', 60);
        $one['historical_assessment']['caveats'] = ['Limited regime coverage.'];
        $two['historical_assessment']['caveats'] = ['Limited regime coverage.'];

        $result = (new AiConsensusEngine)->build(['gemini' => $one, 'groq' => $two], $this->technicalContext(), $this->evidence());

        $this->assertSame(['Limited regime coverage.', 'The mature historical sample is too small for a reliability percentage.'], $result['historical_assessment']['caveats']);
    }

    private function analysis(string $provider, string $gold, string $usd, int $confidence): array
    {
        return [
            'provider' => $provider,
            'model' => 'test-model',
            'status' => 'ready',
            'gold_bias' => $gold,
            'usd_strength' => $usd,
            'risk_level' => 'medium',
            'confidence' => $confidence,
            'summary' => 'Test assessment.',
            'supporting_factors' => [],
            'opposing_factors' => [],
            'risk_factors' => [],
            'historical_assessment' => [
                'sample_quality' => 'insufficient', 'alignment_trend' => 'unclear',
                'regime_fit' => 'unclear', 'summary' => 'Not enough history.', 'caveats' => [],
            ],
            'events' => [],
        ];
    }

    private function technicalContext(): array
    {
        $frames = collect(array_keys(config('horizon.timeframes')))->map(fn (string $key) => [
            'timeframe' => $key,
            'stale' => false,
        ])->all();

        return [
            'gold' => ['required_timeframes_available' => true, 'timeframes' => $frames],
            'usd_proxy' => ['available' => false, 'timeframes' => []],
            'history' => ['overall' => ['sample_quality' => 'insufficient']],
        ];
    }

    private function evidence(): array
    {
        return ['S1' => ['published_at' => now('UTC')->toIso8601String()]];
    }
}
