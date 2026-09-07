<?php

namespace App\Console\Commands;

use App\Services\Market\MarketRefreshService;
use App\Services\Market\MarketMode;
use Illuminate\Console\Command;

class RefreshMarketBias extends Command
{
    protected $signature = 'market:refresh-bias {--timeframe= : Refresh only one configured timeframe}';
    protected $description = 'Fetch completed XAU/USD candles and persist deterministic bias snapshots';

    public function handle(MarketRefreshService $service, MarketMode $mode): int
    {
        if ($mode->effective() !== 'live') {
            $this->info('Skipped: HorizonBias is in demo mode or the production licensing gate is active.');
            return self::SUCCESS;
        }
        $requested = $this->option('timeframe');
        $timeframes = $requested ? [$requested] : array_keys(config('horizon.timeframes'));
        if ($requested && ! array_key_exists($requested, config('horizon.timeframes'))) {
            $this->error("Unsupported timeframe: {$requested}");
            return self::INVALID;
        }
        $failed = false;
        foreach ($timeframes as $timeframe) {
            try {
                $snapshot = $service->refresh($timeframe);
                $this->info("{$timeframe}: {$snapshot->label} ({$snapshot->score})");
            } catch (\Throwable $exception) {
                $failed = true;
                $this->error("{$timeframe}: {$exception->getMessage()}");
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
