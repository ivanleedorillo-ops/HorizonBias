<?php

namespace App\Services\Macro;

use Carbon\CarbonImmutable;

final class AiConsensusEngine
{
    public function build(array $analyses, array $technicalContext, array $evidence): array
    {
        $ready = array_values(array_filter($analyses, fn (array $analysis) => ($analysis['status'] ?? null) === 'ready'));
        $goldBiases = array_column($ready, 'gold_bias');
        $agreement = $this->agreement($goldBiases);
        $goldBias = $this->direction($goldBiases, 'neutral');
        $usdStrength = $this->direction(array_column($ready, 'usd_strength'), 'neutral');
        $riskLevel = $this->highestRisk(array_column($ready, 'risk_level'));
        $events = $this->mergeEvents($ready);
        $confidence = $this->confidence($ready, $agreement, $events, $evidence, $technicalContext);

        return [
            'gold_bias' => $goldBias,
            'label' => ucfirst($goldBias),
            'usd_strength' => $usdStrength,
            'risk_level' => $riskLevel,
            'agreement' => $agreement,
            'confidence' => $confidence,
            'provider_count' => count($ready),
            'summary' => $this->summary($ready, $agreement, $goldBias, $usdStrength),
            'events' => $events,
            'historical_assessment' => $this->historicalAssessment($ready, $technicalContext['history'] ?? []),
            'limitations' => $this->limitations($ready, $evidence, $technicalContext),
        ];
    }

    private function agreement(array $values): string
    {
        if (count($values) < 2) {
            return 'single_model';
        }
        if (count(array_unique($values)) === 1) {
            return 'agree';
        }
        if (in_array('neutral', $values, true)) {
            return 'partially_agree';
        }

        return 'disagree';
    }

    private function direction(array $values, string $neutral): string
    {
        if ($values === []) {
            return $neutral;
        }
        if (count(array_unique($values)) === 1) {
            return $values[0];
        }

        $directional = array_values(array_filter($values, fn (string $value) => $value !== $neutral));

        return count(array_unique($directional)) === 1 ? $directional[0] : $neutral;
    }

    private function highestRisk(array $values): string
    {
        $levels = ['low' => 1, 'medium' => 2, 'high' => 3];
        $highest = 'medium';
        foreach ($values as $value) {
            if (($levels[$value] ?? 0) > $levels[$highest]) {
                $highest = $value;
            }
        }

        return $highest;
    }

    private function mergeEvents(array $ready): array
    {
        $events = [];
        foreach ($ready as $analysis) {
            foreach ($analysis['events'] as $event) {
                $key = $event['source_url'];
                if (! isset($events[$key])) {
                    $events[$key] = $event;
                    unset($events[$key]['citation_id']);
                }
            }
        }

        return array_slice(array_values($events), 0, 6);
    }

    private function confidence(array $ready, string $agreement, array $events, array $evidence, array $technicalContext): int
    {
        $agreementScore = match ($agreement) {
            'agree' => 100,
            'partially_agree' => 65,
            'disagree' => 25,
            default => 45,
        };
        $freshnessScore = $this->evidenceFreshness($evidence);
        $citationTarget = min(3, count($evidence));
        $citationScore = $citationTarget > 0 ? min(100, (int) round(count($events) / $citationTarget * 100)) : 25;
        $technicalScore = $this->technicalAvailability($technicalContext);
        $availabilityScore = count($ready) >= 2 ? 100 : 50;

        $calculated = (int) round(
            ($agreementScore * 0.30)
            + ($freshnessScore * 0.25)
            + ($citationScore * 0.20)
            + ($technicalScore * 0.15)
            + ($availabilityScore * 0.10)
        );
        $reported = $ready === [] ? 0 : (int) round(array_sum(array_column($ready, 'confidence')) / count($ready));
        $confidence = min($calculated, $reported);

        if (count($ready) < 2) {
            $confidence = min($confidence, 55);
        }
        if ($evidence === []) {
            $confidence = min($confidence, 60);
        }
        if ($agreement === 'disagree') {
            $confidence = min($confidence, 45);
        }
        if (! ($technicalContext['gold']['required_timeframes_available'] ?? false)) {
            $confidence = min($confidence, 55);
        }

        return max(0, $confidence);
    }

    private function evidenceFreshness(array $evidence): int
    {
        if ($evidence === []) {
            return 25;
        }

        $maxAge = max(1, (int) config('horizon.macro_feeds.max_age_days', 14));
        $scores = [];
        foreach ($evidence as $item) {
            try {
                $age = CarbonImmutable::parse($item['published_at'])->utc()->diffInHours(now('UTC'));
                $scores[] = max(0, 100 - (int) round($age / ($maxAge * 24) * 100));
            } catch (\Throwable) {
                $scores[] = 0;
            }
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    private function technicalAvailability(array $technicalContext): int
    {
        $gold = $technicalContext['gold']['timeframes'] ?? [];
        if ($gold === []) {
            return 0;
        }

        $fresh = count(array_filter($gold, fn (array $frame) => ! ($frame['stale'] ?? true)));

        return (int) round($fresh / count(config('horizon.timeframes')) * 100);
    }

    private function summary(array $ready, string $agreement, string $goldBias, string $usdStrength): string
    {
        $providers = implode(' and ', array_column($ready, 'provider'));
        $lead = match ($agreement) {
            'agree' => "{$providers} independently agree on a {$goldBias} gold context.",
            'partially_agree' => "{$providers} show partial agreement, producing a cautious {$goldBias} gold lean.",
            'disagree' => "{$providers} disagree on gold direction, so the combined context is neutral.",
            default => "Only {$providers} returned a valid assessment; this is a single-model result.",
        };

        return $lead." The combined USD assessment is {$usdStrength}. This AI consensus remains separate from HorizonBias's deterministic technical score.";
    }

    private function limitations(array $ready, array $evidence, array $technicalContext): array
    {
        $limitations = [];
        if (count($ready) < 2) {
            $limitations[] = 'Only one AI provider returned a valid assessment.';
        }
        if ($evidence === []) {
            $limitations[] = 'No recent verified official-feed evidence was available.';
        }
        if (! ($technicalContext['gold']['required_timeframes_available'] ?? false)) {
            $limitations[] = 'One or more required gold technical timeframes are missing or stale.';
        }
        if (! ($technicalContext['usd_proxy']['available'] ?? false)) {
            $limitations[] = 'No fresh direct USD-proxy technical snapshot was available; USD strength relies on verified macro evidence.';
        }
        $historyQuality = $technicalContext['history']['overall']['sample_quality'] ?? 'insufficient';
        if ($historyQuality === 'insufficient') {
            $limitations[] = 'Historical alignment has not reached the minimum mature sample size.';
        }

        return $limitations;
    }

    private function historicalAssessment(array $ready, array $history): array
    {
        $assessments = array_values(array_filter(array_map(
            fn (array $analysis) => $analysis['historical_assessment'] ?? null,
            $ready,
        ), 'is_array'));
        $sampleQuality = $history['overall']['sample_quality'] ?? 'insufficient';
        $trends = array_column($assessments, 'alignment_trend');
        $fits = array_column($assessments, 'regime_fit');
        $trend = $this->sharedHistoricalValue($trends, 'unclear');
        $regimeFit = $this->sharedHistoricalValue($fits, 'unclear');
        $agreement = count($assessments) < 2
            ? (count($assessments) === 1 ? 'single_model' : 'unavailable')
            : (count(array_unique($trends)) === 1 && count(array_unique($fits)) === 1 ? 'agree' : 'disagree');
        $caveats = collect($assessments)->flatMap(fn (array $assessment) => $assessment['caveats'] ?? [])
            ->filter(fn ($caveat) => is_string($caveat))->unique()->take(6)->values()->all();

        if ($sampleQuality === 'insufficient') {
            $trend = 'unclear';
            $regimeFit = 'unclear';
            $caveats[] = 'The mature historical sample is too small for a reliability percentage.';
        }

        return [
            'sample_quality' => $sampleQuality,
            'alignment_trend' => $trend,
            'regime_fit' => $regimeFit,
            'agreement' => $agreement,
            'summary' => $sampleQuality === 'insufficient'
                ? 'Historical evidence is still accumulating; the two-model interpretation is provisional.'
                : "The analysts describe historical alignment as {$trend} and the current regime fit as {$regimeFit}.",
            'caveats' => array_values(array_unique($caveats)),
        ];
    }

    private function sharedHistoricalValue(array $values, string $fallback): string
    {
        $values = array_values(array_filter($values, fn ($value) => is_string($value)));
        if ($values === [] || count(array_unique($values)) !== 1) {
            return $fallback;
        }

        return $values[0];
    }
}
