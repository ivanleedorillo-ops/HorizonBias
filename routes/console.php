<?php

use Illuminate\Support\Facades\Schedule;

// Run one minute after candle boundaries so providers have time to publish the completed bar.
foreach (config('horizon.timeframes') as $timeframe => $settings) {
    Schedule::command("market:refresh-bias --timeframe={$timeframe}")
        ->cron($settings['refresh_cron'])
        ->withoutOverlapping();
}
Schedule::command('ai:refresh-consensus')->cron(config('horizon.ai.refresh_cron'))->withoutOverlapping();
Schedule::command('bias-history:evaluate')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('bias-history:prune')->dailyAt('02:30')->withoutOverlapping();
