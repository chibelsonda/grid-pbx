<?php

namespace App\Domains\Voicemail\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use GridPbx\Switch\Http\BinaryResponse;

interface SwitchVoicemailMessageGateway
{
    /** @return array<string, mixed> */
    public function changeFolder(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        string $messageResourceId,
        VoicemailMessageFolder $folder,
    ): array;

    /**
     * @param  list<string>  $messageResourceIds
     * @return array{succeeded: list<string>, failed: array<string, string>}
     */
    public function changeFolders(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        array $messageResourceIds,
        VoicemailMessageFolder $folder,
    ): array;

    public function audio(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        string $messageResourceId,
        ?string $range = null,
    ): BinaryResponse;
}
