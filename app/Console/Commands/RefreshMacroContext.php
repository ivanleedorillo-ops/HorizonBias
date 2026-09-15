<?php

namespace App\Console\Commands;

use App\Services\Macro\MacroRefreshService;
use App\Services\System\RefreshRunRecorder;
use Illuminate\Console\Command;

class RefreshMacroContext extends Command
{
    protected $signature = 'macro:refresh';

    protected $description = 'Generate the source-grounded dual-AI macro consensus for gold';

    public function handle(MacroRefreshService $service, RefreshRunRecorder $runs): int
    {
        $run = $runs->start('ai', 'consensus', 'Gemini + Groq GPT-OSS');
        try {
            $brief = $service->refresh();
            $runs->succeed(
                $run,
                $brief->status === 'partial' ? 'partial' : 'success',
                $brief->status === 'partial'
                    ? 'One configured AI provider returned a valid assessment.'
                    : 'Dual-AI context was refreshed successfully.',
            );
            $this->info("AI consensus refreshed: {$brief->stance}, {$brief->agreement}, {$brief->confidence}% confidence ({$brief->status}).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $runs->fail($run, $exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
