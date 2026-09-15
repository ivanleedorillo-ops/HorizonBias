<?php

namespace App\Services\System;

use App\Models\RefreshRun;
use Throwable;

final class RefreshRunRecorder
{
    public function start(string $subsystem, ?string $target, string $provider): RefreshRun
    {
        return RefreshRun::create([
            'subsystem' => $subsystem,
            'target' => $target,
            'provider' => $provider,
            'status' => 'running',
            'started_at' => now('UTC'),
        ]);
    }

    public function succeed(RefreshRun $run, string $status = 'success', ?string $message = null): void
    {
        $run->update([
            'status' => $status,
            'reason_code' => null,
            'safe_message' => $message,
            'finished_at' => now('UTC'),
        ]);
    }

    public function fail(RefreshRun $run, Throwable $exception): void
    {
        [$code, $message] = $this->safeFailure($exception);
        $run->update([
            'status' => 'failed',
            'reason_code' => $code,
            'safe_message' => $message,
            'finished_at' => now('UTC'),
        ]);
    }

    /** @return array{0: string, 1: string} */
    public function safeFailure(Throwable $exception): array
    {
        $message = strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'rate limit'), str_contains($message, 'quota') => ['rate_limited', 'Provider rate limit or application quota reached.'],
            str_contains($message, 'api key'), str_contains($message, 'credential'), str_contains($message, 'configured') => ['configuration', 'Provider credentials or configuration require attention.'],
            str_contains($message, 'timeout'), str_contains($message, 'connection'), str_contains($message, 'offline') => ['connection', 'Provider connection failed or timed out.'],
            str_contains($message, 'invalid'), str_contains($message, 'malformed'), str_contains($message, 'candle'), str_contains($message, 'ohlc') => ['invalid_data', 'Provider data failed validation. The last valid snapshot was retained.'],
            default => ['provider_error', 'Refresh failed. The last valid stored result was retained.'],
        };
    }
}
