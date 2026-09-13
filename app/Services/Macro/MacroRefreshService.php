<?php

namespace App\Services\Macro;

use App\Contracts\MacroContextProvider;
use App\Exceptions\AiProviderException;
use App\Models\BiasSnapshot;
use App\Models\MacroBrief;
use App\Services\Analysis\BiasScorer;
use App\Services\History\ReliabilityService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class MacroRefreshService
{
    public function __construct(
        private readonly GeminiMacroContextProvider $gemini,
        private readonly GroqMacroContextProvider $groq,
        private readonly OfficialMacroFeedProvider $feedProvider,
        private readonly AiConsensusEngine $consensusEngine,
        private readonly AiUsageLimiter $usageLimiter,
        private readonly BiasScorer $biasScorer,
        private readonly ReliabilityService $reliability,
    ) {}

    public function refresh(): MacroBrief
    {
        $technicalContext = $this->technicalContext();
        $evidence = $this->feedProvider->collect();
        $providers = config('horizon.ai.dual_enabled') ? [$this->gemini, $this->groq] : [$this->gemini];
        $analyses = [];
        $providerStatus = [];

        foreach ($providers as $provider) {
            $key = str_starts_with($provider->name(), 'Groq') ? 'groq' : 'gemini';
            if (! $provider->configured()) {
                $providerStatus[$key] = $this->providerStatus($provider, 'unavailable');

                continue;
            }
            try {
                $this->usageLimiter->claim($provider->name());
                $analyses[$key] = $this->normalizeHistoricalAssessment(
                    $provider->generate($technicalContext, $evidence),
                    $technicalContext['history'] ?? [],
                );
                $providerStatus[$key] = $this->providerStatus($provider, 'ready');
            } catch (AiProviderException $exception) {
                $providerStatus[$key] = $this->providerStatus($provider, $exception->category);
                Log::warning('AI macro analyst failed.', [
                    'provider' => $provider->name(),
                    'category' => $exception->category,
                    'error' => $exception->getMessage(),
                ]);
            } catch (\Throwable $exception) {
                $providerStatus[$key] = $this->providerStatus($provider, 'error');
                Log::warning('AI macro analyst failed.', [
                    'provider' => $provider->name(),
                    'category' => 'error',
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($analyses === []) {
            $lastBrief = MacroBrief::query()->latest('generated_at')->latest('id')->first();
            if ($lastBrief) {
                $lastBrief->update(['status' => 'stale']);
            }
            throw new RuntimeException('No AI analyst returned a valid assessment; the last valid brief was retained.');
        }

        $consensus = $this->consensusEngine->build($analyses, $technicalContext, $evidence);
        $events = $consensus['events'];
        $sources = collect($events)->map(fn (array $event) => [
            'name' => $event['source_name'],
            'url' => $event['source_url'],
        ])->unique('url')->values()->all();

        return MacroBrief::create([
            'stance' => $consensus['gold_bias'] === 'neutral' ? 'mixed' : $consensus['gold_bias'],
            'risk_level' => $consensus['risk_level'],
            'summary' => $consensus['summary'],
            'events' => $events,
            'sources' => $sources,
            'analyses' => $analyses,
            'consensus' => $consensus,
            'agreement' => $consensus['agreement'],
            'confidence' => $consensus['confidence'],
            'provider_status' => $providerStatus,
            'evidence_hash' => hash('sha256', json_encode(array_values($evidence), JSON_THROW_ON_ERROR)),
            'prompt_version' => MacroAnalysisSchema::PROMPT_VERSION,
            'generated_at' => now('UTC'),
            'status' => count($analyses) === count($providers) ? 'ready' : 'partial',
        ]);
    }

    private function technicalContext(): array
    {
        return [
            'gold' => $this->technicalContextFor(config('horizon.symbol')),
            'usd_proxy' => $this->technicalContextFor(config('horizon.usd_proxy.symbol')),
            'history' => $this->reliability->contextForAi(),
        ];
    }

    private function technicalContextFor(string $symbol): array
    {
        $latest = BiasSnapshot::query()->where('symbol', $symbol)->latest('generated_at')->get()->unique('timeframe');
        $frames = $latest->map(function (BiasSnapshot $snapshot) {
            $staleAfter = config("horizon.timeframes.{$snapshot->timeframe}.stale_after");
            $stale = $snapshot->status !== 'ready'
                || ! is_numeric($staleAfter)
                || $snapshot->data_as_of->lt(now('UTC')->subMinutes((int) $staleAfter));

            return [
                'timeframe' => $snapshot->timeframe,
                'score' => $snapshot->score,
                'label' => $snapshot->label,
                'component_scores' => $snapshot->component_scores,
                'metrics' => $snapshot->metrics,
                'explanations' => $snapshot->explanations,
                'data_as_of' => $snapshot->data_as_of->toIso8601String(),
                'stale' => $stale,
            ];
        })->values()->all();
        $freshFrames = array_values(array_filter($frames, fn (array $frame) => ! $frame['stale']));
        $freshScores = collect($freshFrames)->mapWithKeys(fn (array $frame) => [$frame['timeframe'] => $frame['score']])->all();
        $required = ['1h', '4h', '1d'];
        $keys = array_column($freshFrames, 'timeframe');

        return [
            'symbol' => $symbol,
            'available' => $freshFrames !== [],
            'required_timeframes_available' => count($freshFrames) >= 4 && collect($required)->every(fn (string $key) => in_array($key, $keys, true)),
            'overall' => $this->biasScorer->overall($freshScores),
            'timeframes' => $frames,
        ];
    }

    private function providerStatus(MacroContextProvider $provider, string $status): array
    {
        return [
            'provider' => $provider->name(),
            'model' => $provider->model(),
            'status' => $status,
            'requests_today' => $this->usageLimiter->attempts($provider->name()),
            'daily_cap' => (int) config('horizon.ai.daily_request_cap'),
        ];
    }

    private function normalizeHistoricalAssessment(array $analysis, array $history): array
    {
        $quality = $history['overall']['sample_quality'] ?? 'insufficient';
        $analysis['historical_assessment']['sample_quality'] = $quality;
        if ($quality === 'insufficient') {
            $analysis['historical_assessment']['alignment_trend'] = 'unclear';
            $analysis['historical_assessment']['regime_fit'] = 'unclear';
        }

        return $analysis;
    }
}
