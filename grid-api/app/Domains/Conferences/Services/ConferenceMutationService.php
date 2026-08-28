<?php

namespace App\Domains\Conferences\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConferenceMutationService
{
    public function __construct(private readonly SwitchConferenceGateway $gateway, private readonly ConferenceProjectionService $projection, private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ipAddress = null): SwitchConference
    {
        $resourceId = null;
        try {
            $snapshot = $this->gateway->create($account, $this->resolve($account, $data));
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;
            if ($resourceId === null) { throw new \UnexpectedValueException('Switch conference create response is missing its identifier.'); }
            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchConference {
                $conference = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'conference.created', 'succeeded', $conference->switch_resource_id, [], $ipAddress, 'conference');
                return $conference;
            });
        } catch (Throwable $exception) {
            if ($resourceId !== null) { try { $this->gateway->delete($account, $resourceId); } catch (Throwable) {} }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchConference $conference, User $actor, array $data, ?string $ipAddress = null): SwitchConference
    {
        $snapshot = $this->gateway->update($account, $conference->switch_resource_id, $this->resolve($account, $data));
        return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchConference {
            $updated = $this->projection->project($account, $snapshot);
            $this->audit->record($actor, $account, 'conference.updated', 'succeeded', $updated->switch_resource_id, [], $ipAddress, 'conference');
            return $updated;
        });
    }

    public function delete(SwitchAccount $account, SwitchConference $conference, User $actor, ?string $ipAddress = null): void
    {
        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsConference($callflow->switch_json['flow'] ?? null, $conference->switch_resource_id)) {
                throw ValidationException::withMessages(['conference' => ['Remove this conference from call routing before deleting it.']]);
            }
        }
        $this->gateway->delete($account, $conference->switch_resource_id);
        DB::transaction(function () use ($account, $actor, $conference, $ipAddress): void {
            $conference->delete();
            $this->audit->record($actor, $account, 'conference.deleted', 'succeeded', $conference->switch_resource_id, [], $ipAddress, 'conference');
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolve(SwitchAccount $account, array $data): array
    {
        $ownerId = $data['owner_id'] ?? null;
        $owner = empty($ownerId) ? null : $account->extensions()->where('id', $ownerId)->first();
        if (! empty($ownerId) && $owner === null) {
            throw ValidationException::withMessages(['owner_id' => ['The selected conference owner is unavailable for this account.']]);
        }
        return [...$data, 'switch_owner_reference' => $owner?->switch_resource_id];
    }

    private function containsConference(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) { return false; }
        if (($node['module'] ?? null) === 'conference' && ($node['data']['id'] ?? null) === $resourceId) { return true; }
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsConference($child, $resourceId)) { return true; }
        }
        return false;
    }
}
