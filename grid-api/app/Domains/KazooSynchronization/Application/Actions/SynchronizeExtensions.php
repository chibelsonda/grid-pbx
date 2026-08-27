<?php

namespace App\Domains\KazooSynchronization\Application\Actions;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use App\Domains\KazooSynchronization\Application\Contracts\KazooUserGateway;
use App\Domains\KazooSynchronization\Domain\ProjectionStatus;
use App\Domains\KazooSynchronization\Domain\SyncRunStatus;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncCheckpoint;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SynchronizeExtensions
{
    public function __construct(private readonly KazooUserGateway $gateway) {}

    public function handle(SyncRun $run): void
    {
        $run->update([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        $account = $run->kazooAccount()->firstOrFail();
        $records = [];

        foreach ($this->gateway->users($account) as $user) {
            $resourceId = $this->stringValue($user['id'] ?? null);

            if ($resourceId === null) {
                continue;
            }

            $records[$resourceId] = $this->mapUser($user);
        }

        DB::transaction(function () use ($run, $account, $records): void {
            $syncedAt = now();
            $resourceIds = array_keys($records);

            foreach ($records as $resourceId => $attributes) {
                $extension = KazooExtension::withTrashed()->firstOrNew([
                    'kazoo_account_id' => $account->getKey(),
                    'kazoo_resource_id' => $resourceId,
                ]);
                $extension->fill($attributes + [
                    'last_synced_at' => $syncedAt,
                    'sync_status' => ProjectionStatus::Healthy,
                    'projection_version' => 1,
                ]);
                $extension->deleted_at = null;
                $extension->save();
            }

            $missing = $account->extensions()
                ->when($resourceIds !== [], fn ($query) => $query->whereNotIn('kazoo_resource_id', $resourceIds))
                ->get();
            $deletedCount = $missing->count();

            KazooExtension::destroy($missing->modelKeys());

            $run->update([
                'status' => SyncRunStatus::Succeeded,
                'processed_count' => count($records),
                'upserted_count' => count($records),
                'deleted_count' => $deletedCount,
                'finished_at' => now(),
            ]);

            SyncCheckpoint::query()->updateOrCreate([
                'kazoo_account_id' => $account->getKey(),
                'resource_type' => 'extensions',
            ], [
                'last_sync_run_id' => $run->getKey(),
                'cursor' => null,
                'status' => ProjectionStatus::Healthy,
                'last_successful_at' => now(),
                'error_message' => null,
            ]);
        });
    }

    /** @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function mapUser(array $user): array
    {
        $firstName = $this->stringValue($user['first_name'] ?? null);
        $lastName = $this->stringValue($user['last_name'] ?? null);
        $username = $this->stringValue($user['username'] ?? null);
        $name = $this->stringValue($user['name'] ?? null);
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName])));
        $presenceId = $this->stringValue($user['presence_id'] ?? null);

        return [
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $name ?? ($fullName !== '' ? $fullName : ($username ?? 'Unnamed extension')),
            'email' => $this->stringValue($user['email'] ?? null),
            'extension' => $this->stringValue(Arr::get($user, 'caller_id.internal.number')) ?? $presenceId,
            'timezone' => $this->stringValue($user['timezone'] ?? null),
            'is_enabled' => (bool) ($user['enabled'] ?? true),
            'source_revision' => $this->stringValue($user['_rev'] ?? null),
            'source_updated_at' => null,
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
