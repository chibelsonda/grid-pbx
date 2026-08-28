<?php

namespace App\Domains\Voicemail\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailMessageGateway;
use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use GridPbx\Switch\Dto\Voicemail\VoicemailMessageFolder as SwitchVoicemailMessageFolder;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\Resources\VoicemailBoxResourceClient;

class CrossbarSwitchVoicemailMessageGateway implements SwitchVoicemailMessageGateway
{
    public function __construct(private readonly VoicemailBoxResourceClient $voicemailBoxes) {}

    public function changeFolder(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        string $messageResourceId,
        VoicemailMessageFolder $folder,
    ): array {
        return $this->voicemailBoxes->changeMessageFolder(
            $account->switch_account_id,
            $voicemailBoxResourceId,
            $messageResourceId,
            SwitchVoicemailMessageFolder::from($folder->value),
        )->toArray();
    }

    public function changeFolders(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        array $messageResourceIds,
        VoicemailMessageFolder $folder,
    ): array {
        $result = $this->voicemailBoxes->changeMessagesFolder(
            $account->switch_account_id,
            $voicemailBoxResourceId,
            $messageResourceIds,
            SwitchVoicemailMessageFolder::from($folder->value),
        );

        return ['succeeded' => $result->succeeded, 'failed' => $result->failed];
    }

    public function audio(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        string $messageResourceId,
        ?string $range = null,
    ): BinaryResponse {
        return $this->voicemailBoxes->messageAudio(
            $account->switch_account_id,
            $voicemailBoxResourceId,
            $messageResourceId,
            $range,
        );
    }
}
