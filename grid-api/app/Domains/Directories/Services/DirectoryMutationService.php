<?php

namespace App\Domains\Directories\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DirectoryMutationService
{
    public function __construct(
        private readonly SwitchDirectoryGateway $gateway,
        private readonly DirectoryProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ipAddress = null): SwitchDirectory
    {
        $members = $this->resolveMembers($account, $data['member_ids']);
        $resourceId = null;

        try {
            $created = $this->gateway->create($account, [...$data, 'flags' => []]);
            $resourceId = $this->resourceId($created);
            $snapshot = $this->gateway->replaceMembers($account, $resourceId, $members);

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchDirectory {
                $directory = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'directory.created', 'succeeded', $directory->switch_resource_id, [], $ipAddress, 'directory');

                return $directory;
            });
        } catch (Throwable $exception) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->replaceMembers($account, $resourceId, []);
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchDirectory $directory, User $actor, array $data, ?string $ipAddress = null): SwitchDirectory
    {
        $members = $this->resolveMembers($account, $data['member_ids']);
        $previous = [
            ...$directory->only(['name', 'confirm_match', 'min_dtmf', 'max_dtmf', 'sort_by']),
            'flags' => $this->stringList($directory->switch_json['flags'] ?? null),
        ];
        $next = [...$data, 'flags' => $previous['flags']];

        try {
            $this->gateway->update($account, $directory->switch_resource_id, $next);
            $snapshot = $this->gateway->replaceMembers($account, $directory->switch_resource_id, $members);

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchDirectory {
                $updated = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'directory.updated', 'succeeded', $updated->switch_resource_id, [], $ipAddress, 'directory');

                return $updated;
            });
        } catch (Throwable $exception) {
            try {
                $this->gateway->update($account, $directory->switch_resource_id, $previous);
            } catch (Throwable) {
            }

            throw $exception;
        }
    }

    public function delete(SwitchAccount $account, SwitchDirectory $directory, User $actor, ?string $ipAddress = null): void
    {
        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsDirectory($callflow->switch_json['flow'] ?? null, $directory->switch_resource_id)) {
                throw ValidationException::withMessages(['directory' => ['Remove this directory from call routing before deleting it.']]);
            }
        }

        $members = $directory->members->mapWithKeys(fn ($member): array => [$member->switch_user_resource_id => $member->switch_callflow_resource_id])->all();
        $this->gateway->replaceMembers($account, $directory->switch_resource_id, []);

        try {
            $this->gateway->delete($account, $directory->switch_resource_id);
        } catch (Throwable $exception) {
            $this->gateway->replaceMembers($account, $directory->switch_resource_id, $members);
            throw $exception;
        }

        DB::transaction(function () use ($account, $actor, $directory, $ipAddress): void {
            $directory->delete();
            $this->audit->record($actor, $account, 'directory.deleted', 'succeeded', $directory->switch_resource_id, [], $ipAddress, 'directory');
        });
    }

    /** @param list<string> $memberIds
     * @return array<string, string>
     */
    private function resolveMembers(SwitchAccount $account, array $memberIds): array
    {
        $extensions = $account->extensions()->whereIn('id', $memberIds)->with('callflows')->get();

        if ($extensions->count() !== count($memberIds)) {
            throw ValidationException::withMessages(['member_ids' => ['One or more selected extensions are unavailable for this account.']]);
        }

        return $extensions->mapWithKeys(function ($extension): array {
            $callflow = $extension->callflows->first();

            if ($callflow === null) {
                throw ValidationException::withMessages(['member_ids' => ['Every directory member must have a projected destination callflow.']]);
            }

            return [$extension->switch_resource_id => $callflow->switch_resource_id];
        })->all();
    }

    /** @param array<string, mixed> $snapshot */
    private function resourceId(array $snapshot): string
    {
        $resourceId = $snapshot['id'] ?? null;

        if (! is_string($resourceId) || $resourceId === '') {
            throw new \UnexpectedValueException('Switch directory response is missing its resource identifier.');
        }

        return $resourceId;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    private function containsDirectory(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) {
            return false;
        }

        if (($node['module'] ?? null) === 'directory' && ($node['data']['id'] ?? null) === $resourceId) {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsDirectory($child, $resourceId)) {
                return true;
            }
        }

        return false;
    }
}
