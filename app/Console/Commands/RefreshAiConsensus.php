<?php

namespace App\Console\Commands;

use App\Services\Macro\MacroRefreshService;
use Illuminate\Console\Command;

class RefreshAiConsensus extends Command
{
    protected $signature = 'ai:refresh-consensus';

    protected $description = 'Run the free-tier-safe Gemini and Groq GPT-OSS consensus workflow';

    public function handle(MacroRefreshService $service): int
    {
        try {
            $brief = $service->refresh();
            $this->info("AI consensus refreshed: {$brief->stance}, {$brief->agreement}, {$brief->confidence}% confidence ({$brief->status}).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
