<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('market:refresh-bias --timeframe=5m')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=15m')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1h')->hourlyAt(3)->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=4h')->cron('5 */4 * * *')->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1d')->hourlyAt(7)->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1w')->cron('9 */6 * * *')->withoutOverlapping();
Schedule::command('market:refresh-bias --timeframe=1mo')->twiceDaily(1, 13)->withoutOverlapping();
Schedule::command('ai:refresh-consensus')->cron('11 */3 * * *')->withoutOverlapping();
Schedule::command('bias-history:evaluate')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('bias-history:prune')->dailyAt('02:30')->withoutOverlapping();
