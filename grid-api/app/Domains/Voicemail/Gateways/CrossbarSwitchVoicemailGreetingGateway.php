<?php

namespace App\Domains\Voicemail\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailGreetingGateway;
use GridPbx\Switch\Dto\Media\MediaWriteData;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\Resources\MediaResourceClient;
use GridPbx\Switch\Resources\VoicemailBoxResourceClient;
use Psr\Http\Message\StreamInterface;

class CrossbarSwitchVoicemailGreetingGateway implements SwitchVoicemailGreetingGateway
{
    public function __construct(
        private readonly MediaResourceClient $media,
        private readonly VoicemailBoxResourceClient $voicemailBoxes,
    ) {}

    public function create(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        string $name,
        string $description,
    ): array {
        return $this->media->create($account->switch_account_id, new MediaWriteData(
            name: $name,
            description: $description,
            sourceId: $voicemailBoxResourceId,
            sourceType: 'voicemail',
        ))->toArray();
    }

    public function upload(
        SwitchAccount $account,
        string $mediaResourceId,
        StreamInterface $stream,
        string $contentType,
        int $contentLength,
    ): array {
        return $this->media->upload(
            $account->switch_account_id,
            $mediaResourceId,
            $stream,
            $contentType,
            $contentLength,
        )->toArray();
    }

    public function assign(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        ?string $mediaResourceId,
    ): array {
        return $this->voicemailBoxes->setUnavailableGreeting(
            $account->switch_account_id,
            $voicemailBoxResourceId,
            $mediaResourceId,
        )->toArray();
    }

    public function audio(SwitchAccount $account, string $mediaResourceId, ?string $range = null): BinaryResponse
    {
        return $this->media->raw($account->switch_account_id, $mediaResourceId, $range);
    }

    public function delete(SwitchAccount $account, string $mediaResourceId): void
    {
        $this->media->delete($account->switch_account_id, $mediaResourceId);
    }
}
