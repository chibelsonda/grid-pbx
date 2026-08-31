<?php

namespace App\Domains\SwitchSynchronization\Commands;

use App\Domains\SwitchSynchronization\Services\PollExtensionProjectionsService;
use Illuminate\Console\Command;

class PollExtensionProjectionsCommand extends Command
{
    protected $signature = 'switch:poll-extensions';

    protected $description = 'Queue due Extension projection synchronization runs when polling is enabled';

    public function handle(PollExtensionProjectionsService $polling): int
    {
        $result = $polling->handle();

        if (! $result['enabled']) {
            $this->components->info('Extension projection polling is disabled.');

            return self::SUCCESS;
        }

        $this->components->info("Scheduled {$result['scheduled']} Extension projection sync run(s).");

        return self::SUCCESS;
    }
}
