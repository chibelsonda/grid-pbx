<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use GridPbx\Switch\Dto\Callflows\CallflowSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CallflowMutationService
{
    public function __construct(
        private readonly SwitchCallflowGateway $gateway,
        private readonly CallflowReferenceResolver $references,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        [$module, $resourceId] = $this->destination($account, null, $data);
        [$assignedPhoneNumbers] = $this->phoneNumberSelection(
            $account,
            null,
            $data['phone_number_ids'],
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->create(
                $account,
                $data['name'],
                $module,
                $resourceId,
                $assignedPhoneNumbers,
            ));

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $callflow = $this->project($account, null, $snapshot);
                $this->reconcilePhoneNumbers($account, $callflow, $data['phone_number_ids']);
                $this->recordSuccess($actor, $account, $callflow, 'callflow.created', $data, $ipAddress);

                return $this->load($callflow);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, null, 'callflow.create_failed', $data, $exception, $ipAddress);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        if ($callflow->is_feature_code) {
            throw ValidationException::withMessages([
                'callflow' => ['Feature-code routes are read-only in the guided editor.'],
            ]);
        }

        [$module, $resourceId] = $this->destination($account, $callflow, $data);
        [$assignedPhoneNumbers, $knownPhoneNumbers] = $this->phoneNumberSelection(
            $account,
            $callflow,
            $data['phone_number_ids'],
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->updateDestination(
                $account,
                $callflow->switch_resource_id,
                $module,
                $resourceId,
                $data['name'],
                $assignedPhoneNumbers,
                $knownPhoneNumbers,
            ));

            return DB::transaction(function () use ($account, $callflow, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->reconcilePhoneNumbers($account, $projected, $data['phone_number_ids']);
                $this->recordSuccess($actor, $account, $projected, 'callflow.updated', $data, $ipAddress);

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, $callflow, 'callflow.update_failed', $data, $exception, $ipAddress);

            throw $exception;
        }
    }

    public function delete(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        $blockers = $this->deletionBlockers($account, $callflow);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['callflow' => $blockers]);
        }

        try {
            $this->gateway->delete($account, $callflow->switch_resource_id);
            DB::transaction(function () use ($account, $callflow, $actor, $ipAddress): void {
                $callflow->delete();
                $this->audit->record(
                    $actor,
                    $account,
                    'callflow.deleted',
                    'succeeded',
                    $callflow->switch_resource_id,
                    ['callflow_id' => $callflow->id, 'name' => $callflow->name],
                    $ipAddress,
                    'callflow',
                );
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, $callflow, 'callflow.delete_failed', [], $exception, $ipAddress);

            throw $exception;
        }
    }

    private function project(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        CallflowSnapshot $snapshot,
    ): SwitchCallflow {
        $callflow ??= SwitchCallflow::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $snapshot->id,
        ]);
        $ownerResourceId = $account->extensions()
            ->whereIn('extension', $snapshot->numbers)
            ->value('switch_resource_id');
        $extensionId = $ownerResourceId === null
            ? null
            : $account->extensions()
                ->where('switch_resource_id', $ownerResourceId)
                ->value('extension_id');

        $callflow->fill([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $snapshot->id,
            'switch_extension_id' => $extensionId,
            'owner_switch_resource_id' => $ownerResourceId,
            'name' => $snapshot->name,
            'numbers' => $snapshot->numbers,
            'patterns' => $snapshot->patterns,
            'flags' => $snapshot->flags,
            'modules' => $snapshot->modules,
            'root_module' => $snapshot->flow?->module,
            'node_count' => $snapshot->nodeCount,
            'max_depth' => $snapshot->maxDepth,
            'is_feature_code' => $snapshot->featureCodeName !== null || $snapshot->featureCodeNumber !== null,
            'feature_code_name' => $snapshot->featureCodeName,
            'feature_code_number' => $snapshot->featureCodeNumber,
            'flow_structure' => $this->references->resolve(
                $account,
                is_array($snapshot->data['flow'] ?? null) ? $snapshot->data['flow'] : null,
            ),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $callflow->exists ? $callflow->projection_version + 1 : 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot->toArray()),
        ]);
        $callflow->deleted_at = null;
        $callflow->save();

        return $callflow;
    }

    /** @param list<string> $selectedPhoneNumberIds */
    private function reconcilePhoneNumbers(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        array $selectedPhoneNumberIds,
    ): void {
        $account->phoneNumbers()
            ->where('assigned_callflow_id', $callflow->getKey())
            ->when(
                $selectedPhoneNumberIds !== [],
                fn ($query) => $query->whereNotIn('id', $selectedPhoneNumberIds),
            )
            ->update(['assigned_callflow_id' => null]);
        $account->phoneNumbers()
            ->whereIn('id', $selectedPhoneNumberIds)
            ->update(['assigned_callflow_id' => $callflow->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{string, string}
     */
    private function destination(SwitchAccount $account, ?SwitchCallflow $callflow, array $data): array
    {
        $type = $data['destination_type'];
        $target = match ($type) {
            'extension' => $account->extensions()->where('id', $data['destination_id'])->firstOrFail(),
            'device' => $account->devices()->where('id', $data['destination_id'])->firstOrFail(),
            'voicemail' => $account->voicemailBoxes()->where('id', $data['destination_id'])->firstOrFail(),
            'callflow' => $account->callflows()
                ->when($callflow !== null, fn ($query) => $query->whereKeyNot($callflow->getKey()))
                ->where('id', $data['destination_id'])
                ->firstOrFail(),
            'media' => $account->media()->where('id', $data['destination_id'])->firstOrFail(),
            'directory' => $account->directories()->where('id', $data['destination_id'])->firstOrFail(),
            'group' => $account->groups()->where('id', $data['destination_id'])->firstOrFail(),
            'queue' => $account->queues()->where('id', $data['destination_id'])->firstOrFail(),
            'menu' => $account->menus()->where('id', $data['destination_id'])->firstOrFail(),
            'conference' => $account->conferences()->where('id', $data['destination_id'])->firstOrFail(),
            'fax_box' => $account->faxBoxes()->where('id', $data['destination_id'])->firstOrFail(),
            'temporal_rule_set' => $account->temporalRuleSets()->where('id', $data['destination_id'])->firstOrFail(),
        };

        return [
            match ($type) {
                'extension' => 'user',
                'device' => 'device',
                'voicemail' => 'voicemail',
                'callflow' => 'callflow',
                'media' => 'play',
                'directory' => 'directory',
                'group' => 'group',
                'queue' => 'acdc_member',
                'menu' => 'menu',
                'conference' => 'conference',
                'fax_box' => 'faxbox',
                'temporal_rule_set' => 'temporal_route',
            },
            $target->switch_resource_id,
        ];
    }

    /**
     * @param  list<string>  $selectedIds
     * @return array{list<string>, list<string>}
     */
    private function phoneNumberSelection(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        array $selectedIds,
    ): array {
        $selected = $account->phoneNumbers()->whereIn('id', $selectedIds)->get();

        if ($selected->count() !== count($selectedIds)) {
            throw ValidationException::withMessages([
                'phone_number_ids' => ['One or more selected phone numbers are unavailable for this account.'],
            ]);
        }

        $conflicts = $selected->filter(fn ($phoneNumber): bool => $phoneNumber->assigned_callflow_id !== null
            && ($callflow === null || $phoneNumber->assigned_callflow_id !== $callflow->getKey()));

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'phone_number_ids' => [sprintf(
                    'The following phone numbers are already assigned to another route: %s.',
                    $conflicts->pluck('number')->implode(', '),
                )],
            ]);
        }

        return [
            $selected->pluck('number')->values()->all(),
            $account->phoneNumbers()->pluck('number')->values()->all(),
        ];
    }

    /** @return list<string> */
    private function deletionBlockers(SwitchAccount $account, SwitchCallflow $callflow): array
    {
        $blockers = [];

        if ($callflow->is_feature_code) {
            $blockers[] = 'Feature-code routes cannot be deleted from the guided editor.';
        }

        if ($callflow->switch_extension_id !== null) {
            $blockers[] = 'This route belongs to an extension and must be removed through the extension workflow.';
        }

        if ($callflow->phoneNumbers()->exists()) {
            $blockers[] = 'Remove all assigned phone numbers before deleting this route.';
        }

        foreach ($account->callflows()->whereKeyNot($callflow->getKey())->get() as $candidate) {
            if ($this->containsReference($candidate->flow_structure, $callflow->id)) {
                $blockers[] = sprintf(
                    'This route is referenced by %s.',
                    $candidate->name ?? ($candidate->numbers[0] ?? 'another route'),
                );
            }
        }

        return array_values(array_unique($blockers));
    }

    /** @param array<string, mixed>|null $node */
    private function containsReference(?array $node, string $targetId): bool
    {
        if ($node === null) {
            return false;
        }

        if (($node['module'] ?? null) === 'callflow') {
            $resolvedId = is_array($node['target'] ?? null) ? ($node['target']['id'] ?? null) : null;

            if ($resolvedId === $targetId || ($node['reference_status'] ?? null) === 'unresolved') {
                return true;
            }
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child) && $this->containsReference($child, $targetId)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function recordSuccess(
        User $actor,
        SwitchAccount $account,
        SwitchCallflow $callflow,
        string $action,
        array $data,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'succeeded',
            $callflow->switch_resource_id,
            [
                'callflow_id' => $callflow->id,
                'destination_type' => $data['destination_type'],
                'destination_id' => $data['destination_id'],
                'name' => $data['name'],
                'phone_number_ids' => $data['phone_number_ids'],
            ],
            $ipAddress,
            'callflow',
        );
    }

    /** @param array<string, mixed> $data */
    private function recordFailure(
        User $actor,
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        string $action,
        array $data,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'failed',
            $callflow?->switch_resource_id,
            [
                'callflow_id' => $callflow?->id,
                'destination_type' => $data['destination_type'] ?? null,
                'destination_id' => $data['destination_id'] ?? null,
                'phone_number_ids' => $data['phone_number_ids'] ?? [],
                'error_type' => $exception::class,
            ],
            $ipAddress,
            'callflow',
        );
    }

    private function load(SwitchCallflow $callflow): SwitchCallflow
    {
        return $callflow->load([
            'extension:extension_id,id,display_name,extension',
            'phoneNumbers:phone_number_id,id,assigned_callflow_id,number,state',
        ]);
    }
}
