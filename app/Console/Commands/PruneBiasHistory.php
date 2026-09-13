<?php

namespace App\Console\Commands;

use App\Models\BiasHistoryPoint;
use Illuminate\Console\Command;

class PruneBiasHistory extends Command
{
    protected $signature = 'bias-history:prune {--dry-run : Report expired history without deleting it}';

    protected $description = 'Prune canonical history beyond the configured retention period';

    public function handle(): int
    {
        $days = max(1, (int) config('horizon.history.retention_days', 730));
        $query = BiasHistoryPoint::query()->where('generated_at', '<', now('UTC')->subDays($days));
        $count = $query->count();
        if (! $this->option('dry-run')) {
            $query->delete();
        }

        $this->info(($this->option('dry-run') ? 'Would prune' : 'Pruned')." {$count} history points older than {$days} days.");

        return self::SUCCESS;
    }
}
