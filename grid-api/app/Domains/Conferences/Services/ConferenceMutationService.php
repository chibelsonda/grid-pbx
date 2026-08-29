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
            $snapshot = $this->gateway->create($account, $this->resolve($account, $data, null));
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;
            if ($resourceId === null) {
                throw new \UnexpectedValueException('Switch conference create response is missing its identifier.');
            }

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchConference {
                $conference = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'conference.created', 'succeeded', $conference->switch_resource_id, [], $ipAddress, 'conference');

                return $conference;
            });
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
    public function update(SwitchAccount $account, SwitchConference $conference, User $actor, array $data, ?string $ipAddress = null): SwitchConference
    {
        $snapshot = $this->gateway->update($account, $conference->switch_resource_id, $this->resolve($account, $data, $conference));

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
    private function resolve(SwitchAccount $account, array $data, ?SwitchConference $conference): array
    {
        $ownerId = $data['owner_id'] ?? null;
        $owner = empty($ownerId) ? null : $account->extensions()->where('id', $ownerId)->first();
        if (! empty($ownerId) && $owner === null) {
            throw ValidationException::withMessages(['owner_id' => ['The selected conference owner is unavailable for this account.']]);
        }
        $current = is_array($conference?->switch_json) ? $conference->switch_json : [];
        $currentMaxMembersMedia = $this->stringValue($current['max_members_media'] ?? null);
        $maxMembersMedia = $this->resolveMediaReference(
            $account,
            $data['max_members_media_id'] ?? null,
            'max_members_media_id',
            $currentMaxMembersMedia,
        );

        return [
            ...$data,
            'switch_owner_reference' => $owner?->switch_resource_id,
            'switch_max_members_media_reference' => $maxMembersMedia,
            'clear_switch_max_members_media' => $conference !== null
                && $currentMaxMembersMedia !== null
                && $maxMembersMedia === null,
            'switch_play_entry_tone' => $this->resolveTone(
                $account,
                $data['play_entry_tone_mode'],
                $data['play_entry_tone_media_id'] ?? null,
                $current['play_entry_tone'] ?? null,
                'play_entry_tone',
            ),
            'switch_play_exit_tone' => $this->resolveTone(
                $account,
                $data['play_exit_tone_mode'],
                $data['play_exit_tone_media_id'] ?? null,
                $current['play_exit_tone'] ?? null,
                'play_exit_tone',
            ),
        ];
    }

    private function resolveMediaReference(
        SwitchAccount $account,
        mixed $publicId,
        string $field,
        mixed $currentReference = null,
    ): ?string {
        if (is_string($publicId) && $publicId !== '') {
            $media = $account->media()->where('id', $publicId)->first();

            if ($media === null) {
                throw ValidationException::withMessages([$field => ['The selected media is unavailable for this account.']]);
            }

            return $media->switch_resource_id;
        }

        $current = $this->stringValue($currentReference);

        if ($current !== null && ! $account->media()->where('switch_resource_id', $current)->exists()) {
            return $current;
        }

        return null;
    }

    private function resolveTone(
        SwitchAccount $account,
        string $mode,
        mixed $publicMediaId,
        mixed $current,
        string $field,
    ): bool|string {
        if ($mode === 'enabled') {
            return true;
        }

        if ($mode === 'disabled') {
            return false;
        }

        if ($mode === 'media') {
            $reference = $this->resolveMediaReference($account, $publicMediaId, "{$field}_media_id");

            if ($reference === null) {
                throw ValidationException::withMessages(["{$field}_media_id" => ['Select a conference tone.']]);
            }

            return $reference;
        }

        if ($mode === 'current_custom' && is_string($current) && $current !== '') {
            return $current;
        }

        throw ValidationException::withMessages([$field => ['The current custom conference tone is unavailable.']]);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function containsConference(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) {
            return false;
        }
        if (($node['module'] ?? null) === 'conference' && ($node['data']['id'] ?? null) === $resourceId) {
            return true;
        }
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsConference($child, $resourceId)) {
                return true;
            }
        }

        return false;
    }
}
