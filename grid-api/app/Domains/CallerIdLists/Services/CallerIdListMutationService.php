<?php

namespace App\Domains\CallerIdLists\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallerIdLists\Contracts\SwitchCallerIdListGateway;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnexpectedValueException;

class CallerIdListMutationService
{
    public function __construct(
        private readonly SwitchCallerIdListGateway $gateway,
        private readonly CallerIdListProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallerIdList {
        $resourceId = null;

        try {
            $created = $this->gateway->create($account, $data);
            $resourceId = is_string($created['id'] ?? null) ? $created['id'] : null;

            if ($resourceId === null) {
                throw new UnexpectedValueException('Switch Caller-ID List create response is missing its identifier.');
            }

            foreach ($data['entries'] as $entry) {
                $this->gateway->createEntry($account, $resourceId, $entry);
            }

            $projected = $this->projection->project($account, $this->gateway->details($account, $resourceId));
            $this->record($actor, $account, $projected, 'caller_id_list.created', $ipAddress);

            return $projected;
        } catch (Throwable $exception) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchCallerIdList $list,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallerIdList {
        $current = $list->entries->keyBy('id');
        $submitted = collect($data['entries']);
        $submittedIds = $submitted->pluck('id')->filter()->values();
        $unknown = $submittedIds->reject(fn (string $id): bool => $current->has($id));

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'entries' => ['One or more Caller-ID List entries are not available in this account.'],
            ]);
        }

        try {
            $this->gateway->update($account, $list->switch_resource_id, $data);

            foreach ($submitted as $entry) {
                $publicId = $entry['id'];

                if ($publicId === null) {
                    $this->gateway->createEntry($account, $list->switch_resource_id, $entry);

                    continue;
                }

                $this->gateway->updateEntry(
                    $account,
                    $list->switch_resource_id,
                    $current->get($publicId)->switch_resource_id,
                    $entry,
                );
            }

            foreach ($current->reject(fn ($entry): bool => $submittedIds->contains($entry->id)) as $entry) {
                $this->gateway->deleteEntry(
                    $account,
                    $list->switch_resource_id,
                    $entry->switch_resource_id,
                );
            }

            $projected = $this->projection->project(
                $account,
                $this->gateway->details($account, $list->switch_resource_id),
            );
            $this->record($actor, $account, $projected, 'caller_id_list.updated', $ipAddress);

            return $projected;
        } catch (Throwable $exception) {
            try {
                $this->projection->project(
                    $account,
                    $this->gateway->details($account, $list->switch_resource_id),
                );
            } catch (Throwable) {
            }

            throw $exception;
        }
    }

    public function delete(
        SwitchAccount $account,
        SwitchCallerIdList $list,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        if ($this->isReferenced($account, $list->id)) {
            throw ValidationException::withMessages([
                'caller_id_list' => ['Remove this list from its Callflow actions before deleting it.'],
            ]);
        }

        $this->gateway->delete($account, $list->switch_resource_id);
        DB::transaction(function () use ($account, $list, $actor, $ipAddress): void {
            $list->delete();
            $this->record($actor, $account, $list, 'caller_id_list.deleted', $ipAddress);
        });
    }

    private function isReferenced(SwitchAccount $account, string $publicListId): bool
    {
        foreach ($account->callflows()->get(['flow_structure']) as $callflow) {
            if ($this->treeContainsList($callflow->flow_structure, $publicListId)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed>|null $node */
    private function treeContainsList(?array $node, string $publicListId): bool
    {
        if ($node === null) {
            return false;
        }

        if (($node['module'] ?? null) === 'cidlistmatch'
            && ($node['settings']['caller_id_list_id'] ?? null) === $publicListId) {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child) && $this->treeContainsList($child, $publicListId)) {
                return true;
            }
        }

        return false;
    }

    private function record(
        User $actor,
        SwitchAccount $account,
        SwitchCallerIdList $list,
        string $action,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'succeeded',
            $list->switch_resource_id,
            ['caller_id_list_id' => $list->id],
            $ipAddress,
            'caller_id_list',
        );
    }
}
