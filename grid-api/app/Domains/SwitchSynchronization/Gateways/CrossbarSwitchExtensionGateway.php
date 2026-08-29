<?php

namespace App\Domains\SwitchSynchronization\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use Generator;
use GridPbx\Switch\Domains\Accounts\AccountResource;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Media\MediaResourceClient;
use GridPbx\Switch\Domains\Voicemail\VoicemailBoxResourceClient;

class CrossbarSwitchExtensionGateway implements SwitchExtensionGateway
{
    public function __construct(
        private readonly AccountResourceClient $resources,
        private readonly DeviceResourceClient $devices,
        private readonly VoicemailBoxResourceClient $voicemailBoxes,
        private readonly MediaResourceClient $media,
    ) {}

    /** @return Generator<int, array<string, mixed>> */
    public function users(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::Users);
    }

    /** @return Generator<int, array<string, mixed>> */
    public function devices(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::Devices);
    }

    public function deviceStatuses(SwitchAccount $account): array
    {
        $statuses = [];

        foreach ($this->devices->statuses($account->switch_account_id) as $status) {
            $statuses[$status->deviceId] = $status->registered;
        }

        return $statuses;
    }

    /** @return Generator<int, array<string, mixed>> */
    public function voicemailBoxes(SwitchAccount $account): Generator
    {
        yield from $this->details($account, AccountResource::VoicemailBoxes);
    }

    /** @return Generator<int, array<string, mixed>> */
    public function voicemailMessages(SwitchAccount $account, string $voicemailBoxResourceId): Generator
    {
        foreach ($this->voicemailBoxes->allMessages(
            $account->switch_account_id,
            $voicemailBoxResourceId,
        ) as $snapshot) {
            yield $snapshot->toArray();
        }
    }

    public function media(SwitchAccount $account, string $mediaResourceId): array
    {
        return $this->media->get($account->switch_account_id, $mediaResourceId)->toArray();
    }

    /** @return Generator<int, CallflowSnapshot> */
    public function callflows(SwitchAccount $account): Generator
    {
        foreach ($this->resources->allDetails($account->switch_account_id, AccountResource::Callflows) as $snapshot) {
            if ($snapshot instanceof CallflowSnapshot) {
                yield $snapshot;
            }
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function details(SwitchAccount $account, AccountResource $resource): Generator
    {
        foreach ($this->resources->allDetails($account->switch_account_id, $resource) as $snapshot) {
            yield $snapshot->toArray();
        }
    }
}
