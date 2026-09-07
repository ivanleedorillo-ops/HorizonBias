<?php

namespace App\Services\Macro;

use App\Contracts\MacroContextProvider;
use App\Models\BiasSnapshot;
use App\Models\MacroBrief;
use Illuminate\Support\Facades\Log;

final class MacroRefreshService
{
    public function __construct(private readonly MacroContextProvider $provider) {}

    public function refresh(): MacroBrief
    {
        $context = BiasSnapshot::query()->latest('generated_at')->get()->unique('timeframe')
            ->map(fn (BiasSnapshot $snapshot) => ['timeframe' => $snapshot->timeframe, 'score' => $snapshot->score, 'label' => $snapshot->label, 'as_of' => $snapshot->data_as_of?->toIso8601String()])
            ->values()->all();

        try {
            $result = $this->provider->generate($context);
            $sources = array_values(array_map(fn ($event) => ['name' => $event['source_name'], 'url' => $event['source_url']], $result['events']));

            return MacroBrief::create([
                'stance' => $result['stance'],
                'risk_level' => $result['risk_level'],
                'summary' => $result['summary'],
                'events' => $result['events'],
                'sources' => $sources,
                'generated_at' => now('UTC'),
                'status' => 'ready',
            ]);
        } catch (\Throwable $exception) {
            MacroBrief::query()->latest('generated_at')->limit(1)->update(['status' => 'stale']);
            Log::warning('Macro context refresh failed.', ['provider' => $this->provider->name(), 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
