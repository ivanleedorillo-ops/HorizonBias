<?php

namespace App\Services\Dashboard;

use App\Models\MacroBrief;
use App\Models\RefreshRun;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Support\Collection;

final class RefreshStatusService
{
    public function build(string $mode, Collection $latest, array $frames, ?MacroBrief $macro): array
    {
        $now = CarbonImmutable::now('UTC');
        $frameData = collect($frames)->keyBy('key');
        $runs = RefreshRun::query()
            ->whereIn('subsystem', ['market', 'ai'])
            ->latest('started_at')
            ->latest('id')
            ->limit(100)
            ->get();

        $timeframes = collect(config('horizon.timeframes'))->map(function (array $settings, string $key) use ($mode, $latest, $frameData, $runs, $now) {
            $snapshot = $latest->get($key);
            $frame = $frameData->get($key);
            $targetRuns = $runs->where('subsystem', 'market')->where('target', $key);
            $lastRun = $targetRuns->first();
            $lastFailure = $targetRuns->firstWhere('status', 'failed');
            $next = $this->nextRun($settings['refresh_cron'], $now);

            $status = match (true) {
                $mode === 'demo' => 'demo',
                ! $snapshot => 'awaiting',
                $lastRun?->status === 'failed' && $lastRun->finished_at?->greaterThan($snapshot->generated_at) => 'failed',
                (bool) ($frame['stale'] ?? true) => 'stale',
                default => 'ready',
            };

            return [
                'key' => $key,
                'label' => $settings['label'],
                'status' => $status,
                'data_as_of' => $frame['data_as_of'] ?? null,
                'completed_at' => $frame['completed_at'] ?? null,
                'last_success_at' => $snapshot?->generated_at?->toIso8601String(),
                'last_attempt_at' => $lastRun?->finished_at?->toIso8601String() ?? $lastRun?->started_at?->toIso8601String(),
                'next_scheduled_at' => $next,
                'failure_code' => $status === 'failed' ? $lastFailure?->reason_code : null,
                'message' => $status === 'failed' ? $lastFailure?->safe_message : $this->frameMessage($status),
            ];
        })->values();

        $marketStatus = match (true) {
            $mode === 'demo' => 'demo',
            $timeframes->contains('status', 'failed') => 'degraded',
            $timeframes->contains('status', 'stale') => 'stale',
            $timeframes->contains('status', 'awaiting') => 'collecting',
            default => 'ready',
        };
        $marketRuns = $runs->where('subsystem', 'market');
        $aiRun = $runs->firstWhere('subsystem', 'ai');
        $quoteFrame = $timeframes->firstWhere('key', '5m') ?? $timeframes->firstWhere('key', '1d');
        $aiStatus = $mode === 'demo' ? 'demo' : ($macro?->status ?? 'unavailable');
        $macroExpired = $macro?->generated_at?->lt($now->subMinutes((int) config('horizon.gemini.refresh_minutes') * 2)) ?? true;
        if ($mode !== 'demo' && $macro && ($macro->status === 'stale' || $macroExpired)) {
            $aiStatus = 'stale';
        }

        return [
            'checked_at' => $now->toIso8601String(),
            'market' => [
                'status' => $marketStatus,
                'last_success_at' => $latest->max('generated_at')?->toIso8601String(),
                'last_attempt_at' => $marketRuns->max('finished_at')?->toIso8601String() ?? $marketRuns->max('started_at')?->toIso8601String(),
                'next_scheduled_at' => $timeframes->min('next_scheduled_at'),
                'rate_limit_status' => $timeframes->contains('failure_code', 'rate_limited') ? 'rate_limited' : 'not_reported',
                'message' => $this->marketMessage($marketStatus),
                'quote' => [
                    'status' => $quoteFrame['status'] ?? 'awaiting',
                    'data_as_of' => $quoteFrame['data_as_of'] ?? null,
                    'completed_at' => $quoteFrame['completed_at'] ?? null,
                ],
                'timeframes' => $timeframes->all(),
            ],
            'ai' => [
                'status' => $aiStatus,
                'last_success_at' => $macro?->generated_at?->toIso8601String(),
                'last_attempt_at' => $aiRun?->finished_at?->toIso8601String() ?? $aiRun?->started_at?->toIso8601String(),
                'next_scheduled_at' => $this->nextRun(config('horizon.ai.refresh_cron'), $now),
                'failure_code' => $aiRun?->status === 'failed' ? $aiRun->reason_code : null,
                'message' => $aiRun?->status === 'failed'
                    ? $aiRun->safe_message
                    : $this->aiMessage($aiStatus),
                'providers' => array_values($macro?->provider_status ?? []),
            ],
        ];
    }

    private function nextRun(string $expression, CarbonImmutable $now): string
    {
        return CarbonImmutable::instance(
            (new CronExpression($expression))->getNextRunDate($now, 0, false, 'UTC'),
        )->utc()->toIso8601String();
    }

    private function frameMessage(string $status): string
    {
        return match ($status) {
            'ready' => 'Latest completed-candle snapshot is within its freshness window.',
            'stale' => 'Stored analysis is older than this timeframe freshness window.',
            'demo' => 'Illustrative fixture; no provider refresh is scheduled.',
            default => 'Waiting for the first successful completed-candle refresh.',
        };
    }

    private function marketMessage(string $status): string
    {
        return match ($status) {
            'ready' => 'All stored timeframe snapshots are within their configured freshness windows.',
            'degraded' => 'A recent provider refresh failed; last valid snapshots remain visible.',
            'stale' => 'One or more stored timeframe snapshots are stale.',
            'demo' => 'Demo mode uses fixed illustrative data and does not call the market provider.',
            default => 'Market history is still collecting the required timeframe snapshots.',
        };
    }

    private function aiMessage(string $status): string
    {
        return match ($status) {
            'ready' => 'Both configured AI analysts returned a validated assessment.',
            'partial' => 'Only one configured AI analyst returned a validated assessment.',
            'stale' => 'The last valid AI assessment is being retained and is marked stale.',
            'demo' => 'Illustrative AI content; no model request is made in demo mode.',
            default => 'No validated AI assessment is currently available.',
        };
    }
}
