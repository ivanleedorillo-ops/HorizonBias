<?php

namespace App\Console\Commands;

use App\Services\History\OutcomeEvaluator;
use Illuminate\Console\Command;

class EvaluateBiasHistory extends Command
{
    protected $signature = 'bias-history:evaluate {--scope= : Evaluate one timeframe or overall} {--limit=1000}';

    protected $description = 'Evaluate mature canonical bias history using stored completed candles only';

    public function handle(OutcomeEvaluator $evaluator): int
    {
        $scope = $this->option('scope');
        $allowed = [...array_keys(config('horizon.timeframes')), 'overall'];
        if ($scope !== null && ! in_array($scope, $allowed, true)) {
            $this->error("Unsupported history scope: {$scope}");

            return self::INVALID;
        }

        $result = $evaluator->evaluate($scope, max(1, (int) $this->option('limit')));
        $this->info("Outcomes: {$result['evaluated']} evaluated, {$result['pending']} pending, {$result['unavailable']} unavailable.");

        return self::SUCCESS;
    }
}
