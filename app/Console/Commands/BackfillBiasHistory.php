<?php

namespace App\Console\Commands;

use App\Services\History\BiasHistoryService;
use Illuminate\Console\Command;

class BackfillBiasHistory extends Command
{
    protected $signature = 'bias-history:backfill';

    protected $description = 'Build canonical history points from stored bias snapshots without calling external providers';

    public function handle(BiasHistoryService $history): int
    {
        $result = $history->backfill();
        $this->info("Bias history ready: {$result['created']} created, {$result['total']} total canonical points.");

        return self::SUCCESS;
    }
}
