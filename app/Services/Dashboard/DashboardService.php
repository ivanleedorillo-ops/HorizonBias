<?php

namespace App\Services\Dashboard;

use App\Models\MacroBrief;
use App\Services\Analysis\BiasScorer;
use App\Services\Analysis\LatestBiasSnapshots;
use App\Services\Market\MarketMode;

final class DashboardService
{
    public function __construct(
        private readonly MarketMode $mode,
        private readonly BiasScorer $scorer,
        private readonly DemoDashboard $demo,
        private readonly LatestBiasSnapshots $latestSnapshots,
    ) {}

    public function data(): array
    {
        if ($this->mode->effective() === 'demo') {
            return $this->demo->data($this->mode->licensingGateApplied());
        }

        $latest = $this->latestSnapshots->forSymbol(config('horizon.symbol'));
        $frames = [];
        $scores = [];
        foreach (config('horizon.timeframes') as $key => $settings) {
            $snapshot = $latest->get($key);
            if (! $snapshot) {
                continue;
            }
            $stale = $snapshot->status !== 'ready' || $snapshot->data_as_of->lt(now('UTC')->subMinutes($settings['stale_after']));
            if (! $stale) {
                $scores[$key] = $snapshot->score;
            }
            $frames[] = [
                'key' => $key, 'label' => $settings['label'], 'score' => $snapshot->score, 'bias' => $snapshot->label,
                'component_scores' => $snapshot->component_scores, 'metrics' => $snapshot->metrics, 'explanations' => $snapshot->explanations,
                'data_as_of' => $snapshot->data_as_of->toIso8601String(), 'stale' => $stale, 'status' => $stale ? 'stale' : 'ready',
            ];
        }

        $overall = $this->scorer->overall($scores);
        $macro = MacroBrief::query()->latest('generated_at')->first();
        $fiveMinute = $latest->get('5m');
        $daily = $latest->get('1d');
        $price = $fiveMinute?->metrics['close'] ?? $daily?->metrics['close'] ?? null;
        $macroData = $this->macroData($macro);

        return [
            'mode' => $overall ? 'live' : 'unavailable',
            'notice' => $overall ? null : 'Live analysis is incomplete. Waiting for required 1h, 4h, 1d and one additional timeframe.',
            'symbol' => config('horizon.symbol'),
            'quote' => ['price' => $price, 'currency' => 'USD', 'change' => null, 'change_percent' => null, 'as_of' => $fiveMinute?->data_as_of?->toIso8601String() ?? $daily?->data_as_of?->toIso8601String()],
            'overall' => $overall ? ['score' => $overall['score'], 'label' => $overall['label'], 'summary' => 'Deterministic weighted agreement across available timeframes.', 'generated_at' => $latest->max('generated_at')?->toIso8601String(), 'stale' => collect($frames)->contains('stale', true)] : null,
            'timeframes' => $frames,
            'macro' => $macroData,
            'system' => ['market_provider' => config('horizon.market_provider'), 'ai_provider' => 'Gemini + Groq GPT-OSS', 'licensing_gate_applied' => false],
        ];
    }

    private function macroData(?MacroBrief $macro): array
    {
        if (! $macro) {
            return [
                'stance' => 'mixed',
                'gold_bias' => 'neutral',
                'usd_strength' => 'neutral',
                'risk_level' => 'medium',
                'confidence' => 0,
                'agreement' => 'unavailable',
                'summary' => 'Dual-AI context is not available yet.',
                'limitations' => ['Run the AI consensus refresh after configuring at least one AI provider.'],
                'analyses' => [],
                'historical_assessment' => [
                    'sample_quality' => 'insufficient', 'alignment_trend' => 'unclear',
                    'regime_fit' => 'unclear', 'agreement' => 'unavailable',
                    'summary' => 'Historical evidence is not available yet.', 'caveats' => [],
                ],
                'provider_status' => [],
                'events' => [],
                'generated_at' => null,
                'stale' => true,
                'status' => 'unavailable',
            ];
        }

        $consensus = $macro->consensus ?? [];
        $analyses = collect($macro->analyses ?? [])->map(function (array $analysis) {
            return [
                'provider' => $analysis['provider'] ?? 'Unknown provider',
                'model' => $analysis['model'] ?? null,
                'status' => $analysis['status'] ?? 'ready',
                'gold_bias' => $analysis['gold_bias'] ?? 'neutral',
                'usd_strength' => $analysis['usd_strength'] ?? 'neutral',
                'risk_level' => $analysis['risk_level'] ?? 'medium',
                'confidence' => (int) ($analysis['confidence'] ?? 0),
                'summary' => $analysis['summary'] ?? '',
                'supporting_factors' => $analysis['supporting_factors'] ?? [],
                'opposing_factors' => $analysis['opposing_factors'] ?? [],
                'risk_factors' => $analysis['risk_factors'] ?? [],
                'historical_assessment' => $analysis['historical_assessment'] ?? null,
                'citation_ids' => collect($analysis['events'] ?? [])->pluck('citation_id')->filter()->values()->all(),
            ];
        })->values()->all();
        $expired = $macro->generated_at->lt(now('UTC')->subMinutes(config('horizon.gemini.refresh_minutes') * 2));

        return [
            'stance' => $macro->stance,
            'gold_bias' => $consensus['gold_bias'] ?? ($macro->stance === 'mixed' ? 'neutral' : $macro->stance),
            'usd_strength' => $consensus['usd_strength'] ?? 'neutral',
            'risk_level' => $macro->risk_level,
            'confidence' => (int) ($macro->confidence ?? $consensus['confidence'] ?? 0),
            'agreement' => $macro->agreement ?? $consensus['agreement'] ?? 'legacy',
            'summary' => $macro->summary,
            'limitations' => $consensus['limitations'] ?? [],
            'analyses' => $analyses,
            'historical_assessment' => $consensus['historical_assessment'] ?? [
                'sample_quality' => 'insufficient', 'alignment_trend' => 'unclear',
                'regime_fit' => 'unclear', 'agreement' => 'unavailable',
                'summary' => 'Historical evidence predates this assessment.', 'caveats' => [],
            ],
            'provider_status' => array_values($macro->provider_status ?? []),
            'events' => $macro->events,
            'generated_at' => $macro->generated_at->toIso8601String(),
            'stale' => $macro->status === 'stale' || $expired,
            'status' => $expired ? 'stale' : $macro->status,
        ];
    }
}
