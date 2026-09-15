<?php

namespace App\Console\Commands;

use App\Services\Market\MarketMode;
use App\Services\Market\MarketRefreshService;
use App\Services\System\RefreshRunRecorder;
use Illuminate\Console\Command;

class RefreshMarketBias extends Command
{
    protected $signature = 'market:refresh-bias {--timeframe= : Refresh only one configured timeframe}';

    protected $description = 'Fetch completed XAU/USD candles and persist deterministic bias snapshots';

    public function handle(MarketRefreshService $service, MarketMode $mode, RefreshRunRecorder $runs): int
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
            $run = $runs->start('market', $timeframe, (string) config('horizon.market_provider'));
            try {
                $snapshot = $service->refresh($timeframe);
                $runs->succeed($run, 'success', 'Stored a validated completed-candle bias snapshot.');
                $this->info("{$timeframe}: {$snapshot->label} ({$snapshot->score})");
            } catch (\Throwable $exception) {
                $runs->fail($run, $exception);
                $failed = true;
                $this->error("{$timeframe}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
