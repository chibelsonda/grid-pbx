<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardOverviewService
{
    public function __construct(private CallActivityTrendService $callActivityTrends) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account): array
    {
        $inventory = $this->inventory($account);
        $synchronization = $this->synchronization($account);

        return [
            'generated_at' => now()->toIso8601String(),
            'data_as_of' => $synchronization['last_successful_at'] ?? $account->last_synced_at?->toIso8601String(),
            'is_stale' => in_array($synchronization['status'], ['attention', 'error', 'not_started'], true),
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'timezone' => $this->timezone($account),
                'sync_status' => $account->sync_status,
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            ],
            'synchronization' => $synchronization,
            'inventory' => $inventory,
            'calls_today' => $this->callActivityTrends->get($account, 'today')['totals'],
            'attention' => $this->attention($inventory, $synchronization),
        ];
    }

    /** @return array<string, array<string, int>> */
    private function inventory(SwitchAccount $account): array
    {
        $account->loadCount([
            'extensions',
            'extensions as enabled_extensions_count' => fn (Builder $query) => $query->where('is_enabled', true),
            'devices',
            'devices as enabled_devices_count' => fn (Builder $query) => $query->where('is_enabled', true),
            'devices as registered_devices_count' => fn (Builder $query) => $query->where('registration_status', DeviceRegistrationStatus::Registered->value),
            'devices as unregistered_devices_count' => fn (Builder $query) => $query->where('registration_status', DeviceRegistrationStatus::Unregistered->value),
            'devices as enabled_unregistered_devices_count' => fn (Builder $query) => $query
                ->where('is_enabled', true)
                ->where('registration_status', DeviceRegistrationStatus::Unregistered->value),
            'phoneNumbers',
            'phoneNumbers as assigned_phone_numbers_count' => fn (Builder $query) => $query->whereNotNull('assigned_callflow_id'),
            'callflows',
            'callflows as unhealthy_callflows_count' => fn (Builder $query) => $query->whereIn('sync_status', [ProjectionStatus::Stale->value, ProjectionStatus::Error->value]),
            'voicemailBoxes',
            'voicemailMessages as new_voicemail_messages_count' => fn (Builder $query) => $query->where('folder', 'new'),
            'queues',
        ]);

        $extensionTotal = $this->count($account, 'extensions_count');
        $enabledExtensions = $this->count($account, 'enabled_extensions_count');
        $deviceTotal = $this->count($account, 'devices_count');
        $enabledDevices = $this->count($account, 'enabled_devices_count');
        $registeredDevices = $this->count($account, 'registered_devices_count');
        $unregisteredDevices = $this->count($account, 'unregistered_devices_count');
        $enabledUnregisteredDevices = $this->count($account, 'enabled_unregistered_devices_count');
        $phoneNumberTotal = $this->count($account, 'phone_numbers_count');
        $assignedPhoneNumbers = $this->count($account, 'assigned_phone_numbers_count');
        $callflowTotal = $this->count($account, 'callflows_count');
        $unhealthyCallflows = $this->count($account, 'unhealthy_callflows_count');

        return [
            'extensions' => [
                'total' => $extensionTotal,
                'enabled' => $enabledExtensions,
                'disabled' => $extensionTotal - $enabledExtensions,
            ],
            'devices' => [
                'total' => $deviceTotal,
                'enabled' => $enabledDevices,
                'disabled' => $deviceTotal - $enabledDevices,
                'registered' => $registeredDevices,
                'unregistered' => $unregisteredDevices,
                'enabled_unregistered' => $enabledUnregisteredDevices,
                'unknown_registration' => $deviceTotal - $registeredDevices - $unregisteredDevices,
            ],
            'phone_numbers' => [
                'total' => $phoneNumberTotal,
                'assigned' => $assignedPhoneNumbers,
                'unassigned' => $phoneNumberTotal - $assignedPhoneNumbers,
            ],
            'callflows' => [
                'total' => $callflowTotal,
                'healthy' => $callflowTotal - $unhealthyCallflows,
                'attention' => $unhealthyCallflows,
            ],
            'voicemail' => [
                'boxes' => $this->count($account, 'voicemail_boxes_count'),
                'new_messages' => $this->count($account, 'new_voicemail_messages_count'),
            ],
            'queues' => [
                'total' => $this->count($account, 'queues_count'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function synchronization(SwitchAccount $account): array
    {
        /** @var Collection<int, SyncCheckpoint> $checkpoints */
        $checkpoints = $account->syncCheckpoints()
            ->select(['sync_checkpoint_id', 'resource_type', 'status', 'last_successful_at'])
            ->orderBy('resource_type')
            ->get();
        $statusCounts = collect(ProjectionStatus::cases())
            ->mapWithKeys(fn (ProjectionStatus $status): array => [
                $status->value => $checkpoints->where('status', $status)->count(),
            ])
            ->all();
        $activeRuns = $account->syncRuns()
            ->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])
            ->count();
        $lastSuccessfulAt = $checkpoints
            ->filter(fn (SyncCheckpoint $checkpoint): bool => $checkpoint->last_successful_at !== null)
            ->sortByDesc('last_successful_at')
            ->first()?->last_successful_at;

        return [
            'status' => $this->synchronizationStatus($checkpoints, $statusCounts, $activeRuns),
            'last_successful_at' => $lastSuccessfulAt?->toIso8601String(),
            'active_runs' => $activeRuns,
            'checkpoints' => [
                'total' => $checkpoints->count(),
                ...$statusCounts,
            ],
            'resources_requiring_attention' => $checkpoints
                ->filter(fn (SyncCheckpoint $checkpoint): bool => in_array($checkpoint->status, [ProjectionStatus::Stale, ProjectionStatus::Error], true))
                ->map(fn (SyncCheckpoint $checkpoint): array => [
                    'resource' => $checkpoint->resource_type,
                    'status' => $checkpoint->status->value,
                    'last_successful_at' => $checkpoint->last_successful_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'recent_runs' => $account->syncRuns()
                ->select(['sync_run_id', 'id', 'resource_type', 'status', 'processed_count', 'started_at', 'finished_at', 'created_at'])
                ->latest('sync_run_id')
                ->limit(5)
                ->get()
                ->map(fn (SyncRun $run): array => [
                    'id' => $run->id,
                    'resource' => $run->resource_type,
                    'status' => $run->status->value,
                    'processed_count' => $run->processed_count,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'finished_at' => $run->finished_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, SyncCheckpoint>  $checkpoints
     * @param  array<string, int>  $statusCounts
     */
    private function synchronizationStatus(Collection $checkpoints, array $statusCounts, int $activeRuns): string
    {
        if (($statusCounts[ProjectionStatus::Error->value] ?? 0) > 0) {
            return 'error';
        }

        if ($activeRuns > 0 || ($statusCounts[ProjectionStatus::Syncing->value] ?? 0) > 0) {
            return 'syncing';
        }

        if (($statusCounts[ProjectionStatus::Stale->value] ?? 0) > 0) {
            return 'attention';
        }

        return $checkpoints->isEmpty() ? 'not_started' : 'healthy';
    }

    /**
     * @param  array<string, array<string, int>>  $inventory
     * @param  array<string, mixed>  $synchronization
     * @return array{total: int, items: array<int, array<string, int|string>>}
     */
    private function attention(array $inventory, array $synchronization): array
    {
        $items = [];
        $syncErrors = (int) ($synchronization['checkpoints']['error'] ?? 0);
        $staleSyncs = (int) ($synchronization['checkpoints']['stale'] ?? 0);

        if ($syncErrors > 0) {
            $items[] = $this->attentionItem(
                'failed_synchronizations',
                'danger',
                'Failed synchronizations',
                $syncErrors,
                'One or more projected resources could not be refreshed.',
                'Open the affected resource and retry its synchronization after checking Switch connectivity.',
                'system-status',
            );
        }

        if ($staleSyncs > 0) {
            $items[] = $this->attentionItem(
                'stale_projections',
                'warning',
                'Stale projections',
                $staleSyncs,
                'Some dashboard values may no longer match Switch.',
                'Synchronize the affected resource before making an operational decision.',
                'system-status',
            );
        }

        if ($inventory['devices']['enabled_unregistered'] > 0) {
            $items[] = $this->attentionItem(
                'unregistered_devices',
                'warning',
                'Unregistered devices',
                $inventory['devices']['enabled_unregistered'],
                'Confirmed endpoints are currently not registered.',
                'Review SIP credentials, network connectivity, and provisioning state.',
                'devices',
            );
        }

        if ($inventory['phone_numbers']['unassigned'] > 0) {
            $items[] = $this->attentionItem(
                'unassigned_phone_numbers',
                'info',
                'Unassigned phone numbers',
                $inventory['phone_numbers']['unassigned'],
                'These numbers do not currently resolve to a projected callflow.',
                'Assign an intended destination or confirm that the number should remain unused.',
                'phone-numbers',
            );
        }

        if ($inventory['callflows']['attention'] > 0) {
            $items[] = $this->attentionItem(
                'callflow_projection_issues',
                'warning',
                'Callflows requiring attention',
                $inventory['callflows']['attention'],
                'One or more callflow projections are stale or in error.',
                'Synchronize and inspect the route before changing its nodes.',
                'call-routing',
            );
        }

        if (($synchronization['status'] ?? null) === 'not_started') {
            $items[] = $this->attentionItem(
                'synchronization_not_started',
                'info',
                'Synchronization not started',
                1,
                'No resource synchronization checkpoint is available yet.',
                'Run the initial resource synchronizations to populate the operational dashboard.',
                'system-status',
            );
        }

        return ['total' => count($items), 'items' => $items];
    }

    /** @return array<string, int|string> */
    private function attentionItem(
        string $code,
        string $severity,
        string $label,
        int $count,
        string $message,
        string $guidance,
        string $resource,
    ): array {
        return compact('code', 'severity', 'label', 'count', 'message', 'guidance', 'resource');
    }

    private function count(SwitchAccount $account, string $attribute): int
    {
        return (int) $account->getAttribute($attribute);
    }

    private function timezone(SwitchAccount $account): string
    {
        $timezone = $account->timezone;

        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }
}
