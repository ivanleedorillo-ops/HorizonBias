<?php

namespace App\Services\Market;

use App\Contracts\MarketDataProvider;
use App\Models\BiasSnapshot;
use App\Models\MarketCandle;
use App\Services\Analysis\BiasScorer;
use App\Services\History\BiasHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class MarketRefreshService
{
    public function __construct(
        private readonly MarketDataProvider $provider,
        private readonly BiasScorer $scorer,
        private readonly MarketMode $mode,
        private readonly ?BiasHistoryService $history = null,
    ) {}

    public function refresh(string $timeframe): BiasSnapshot
    {
        if ($this->mode->effective() !== 'live') {
            throw new RuntimeException('Market refresh is disabled while HorizonBias is in demo mode.');
        }

        try {
            $candles = $this->provider->fetch($timeframe);
            $result = $this->scorer->score($candles);

            return DB::transaction(function () use ($candles, $timeframe, $result) {
                $now = now('UTC');
                $rows = array_map(fn ($candle) => [
                    'provider' => $this->provider->name(),
                    'symbol' => config('horizon.symbol'),
                    'timeframe' => $timeframe,
                    'opened_at' => $candle->openedAt,
                    'open' => $candle->open,
                    'high' => $candle->high,
                    'low' => $candle->low,
                    'close' => $candle->close,
                    'volume' => $candle->volume,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $candles);

                MarketCandle::upsert(
                    $rows,
                    ['provider', 'symbol', 'timeframe', 'opened_at'],
                    ['open', 'high', 'low', 'close', 'volume', 'updated_at'],
                );

                $snapshot = BiasSnapshot::create([
                    'symbol' => config('horizon.symbol'),
                    'timeframe' => $timeframe,
                    'score' => $result['score'],
                    'label' => $result['label'],
                    'component_scores' => $result['component_scores'],
                    'metrics' => $result['metrics'],
                    'explanations' => $result['explanations'],
                    'provider' => $this->provider->name(),
                    'data_as_of' => end($candles)->openedAt,
                    'generated_at' => $now,
                    'status' => 'ready',
                ]);

                ($this->history ?? app(BiasHistoryService::class))->capture($snapshot);

                return $snapshot;
            });
        } catch (\Throwable $exception) {
            BiasSnapshot::query()->where('timeframe', $timeframe)->latest('generated_at')->limit(1)->update(['status' => 'stale']);
            Log::warning('Market bias refresh failed.', ['timeframe' => $timeframe, 'provider' => $this->provider->name(), 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
