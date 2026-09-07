<?php

namespace App\Console\Commands;

use App\Services\Macro\MacroRefreshService;
use Illuminate\Console\Command;

class RefreshMacroContext extends Command
{
    protected $signature = 'macro:refresh';
    protected $description = 'Generate a source-grounded Gemini macro context brief for gold';

    public function handle(MacroRefreshService $service): int
    {
        if (! config('horizon.gemini.api_key')) {
            $this->info('Skipped: Gemini API key is not configured; the current demo or last-known-good brief is unchanged.');
            return self::SUCCESS;
        }
        try {
            $brief = $service->refresh();
            $this->info("Macro context refreshed: {$brief->stance}, {$brief->risk_level} risk.");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
