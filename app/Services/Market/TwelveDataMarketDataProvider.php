<?php

namespace App\Services\Market;

use App\Contracts\MarketDataProvider;
use App\Data\Candle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TwelveDataMarketDataProvider implements MarketDataProvider
{
    public function name(): string
    {
        return 'Twelve Data';
    }

    public function fetch(string $timeframe): array
    {
        $settings = config("horizon.timeframes.{$timeframe}");
        if (! $settings) {
            throw new RuntimeException("Unsupported timeframe: {$timeframe}");
        }

        $apiKey = config('horizon.twelve_data.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Twelve Data API key is not configured.');
        }

        try {
            $response = Http::baseUrl(config('horizon.twelve_data.base_url'))
                ->acceptJson()
                ->timeout(12)
                ->connectTimeout(5)
                ->retry(2, 250, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->get('/time_series', [
                    'symbol' => config('horizon.symbol'),
                    'interval' => $settings['interval'],
                    'outputsize' => 260,
                    'timezone' => 'UTC',
                    'apikey' => $apiKey,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Market data provider connection failed.', previous: $exception);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('Market data provider credentials were rejected.');
        }
        if ($response->status() === 429) {
            throw new RuntimeException('Market data provider rate limit reached.');
        }
        if (! $response->successful()) {
            throw new RuntimeException("Market data provider returned HTTP {$response->status()}.");
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['status'] ?? 'ok') === 'error' || ! is_array($payload['values'] ?? null)) {
            throw new RuntimeException('Market data provider returned an invalid response.');
        }

        $candles = [];
        $rejectedInvariantCount = 0;
        foreach ($payload['values'] as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Market data response contains a malformed candle.');
            }
            foreach (['datetime', 'open', 'high', 'low', 'close'] as $field) {
                if (! array_key_exists($field, $row) || ($field !== 'datetime' && ! is_numeric($row[$field]))) {
                    throw new RuntimeException("Market data candle is missing {$field}.");
                }
            }

            $open = (float) $row['open'];
            $high = (float) $row['high'];
            $low = (float) $row['low'];
            $close = (float) $row['close'];
            if (! $this->isFinite($open, $high, $low, $close)) {
                throw new RuntimeException('Market data candle violates OHLC invariants.');
            }
            if ($high < max($open, $close, $low) || $low > min($open, $close, $high)) {
                $rejectedInvariantCount++;
                continue;
            }

            try {
                $openedAt = CarbonImmutable::parse($row['datetime'], 'UTC')->utc();
            } catch (\Throwable $exception) {
                throw new RuntimeException('Market data candle has an invalid timestamp.', previous: $exception);
            }

            $candles[] = new Candle(
                $openedAt,
                $open,
                $high,
                $low,
                $close,
                isset($row['volume']) && is_numeric($row['volume']) ? (float) $row['volume'] : null,
            );
        }

        if ($rejectedInvariantCount > 0) {
            Log::warning('Rejected market data candles with invalid OHLC invariants.', [
                'provider' => $this->name(),
                'timeframe' => $timeframe,
                'rejected_count' => $rejectedInvariantCount,
            ]);
        }

        usort($candles, fn (Candle $a, Candle $b) => $a->openedAt <=> $b->openedAt);
        $candles = array_values(array_filter($candles, fn (Candle $candle, int $index) => $index === 0 || $candle->openedAt->notEqualTo($candles[$index - 1]->openedAt), ARRAY_FILTER_USE_BOTH));
        array_pop($candles); // Provider's newest bar may still be forming.

        if (count($candles) < 205) {
            throw new RuntimeException('Market data provider returned fewer than 205 completed candles.');
        }

        return array_slice($candles, -250);
    }

    private function isFinite(float ...$values): bool
    {
        return count(array_filter($values, is_finite(...))) === count($values);
    }
}
