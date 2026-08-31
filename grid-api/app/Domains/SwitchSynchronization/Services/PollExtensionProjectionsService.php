<?php

namespace App\Domains\SwitchSynchronization\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Builder;

class PollExtensionProjectionsService
{
    public function __construct(
        private readonly StartExtensionSyncService $startSync,
    ) {}

    /** @return array{enabled: bool, scheduled: int} */
    public function handle(): array
    {
        if (! config('switch.extension_polling.enabled', false)) {
            return ['enabled' => false, 'scheduled' => 0];
        }

        $intervalMinutes = max(1, (int) config('switch.extension_polling.interval_minutes', 15));
        $batchSize = min(100, max(1, (int) config('switch.extension_polling.batch_size', 10)));
        $freshAfter = now()->subMinutes($intervalMinutes);
        $accounts = SwitchAccount::query()
            ->where('is_enabled', true)
            ->whereDoesntHave('syncCheckpoints', function (Builder $query) use ($freshAfter): void {
                $query->where('resource_type', 'extensions')
                    ->where(function (Builder $checkpoint) use ($freshAfter): void {
                        $checkpoint->where('status', 'syncing')
                            ->orWhere('updated_at', '>', $freshAfter)
                            ->orWhere('last_successful_at', '>', $freshAfter);
                    });
            })
            ->orderBy('account_id')
            ->limit($batchSize)
            ->get();

        foreach ($accounts as $account) {
            $this->startSync->handle($account);
        }

        return ['enabled' => true, 'scheduled' => $accounts->count()];
    }
}
