<?php

use Illuminate\Support\Facades\Schedule;

// Run one minute after candle boundaries so providers have time to publish the completed bar.
Schedule::command('market:refresh-bias --timeframe=5m')->cron('1-59/5 * * * *')->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=15m')->cron('1,16,31,46 * * * *')->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1h')->hourlyAt(3)->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=4h')->cron('5 */4 * * *')->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1d')->hourlyAt(7)->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1w')->cron('9 */6 * * *')->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1mo')->twiceDaily(1, 13)->withoutOverlapping();
Schedule::command('ai:refresh-consensus')->cron('11 */3 * * *')->withoutOverlapping();
Schedule::command('bias-history:evaluate')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('bias-history:prune')->dailyAt('02:30')->withoutOverlapping();
