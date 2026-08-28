<?php

namespace App\Domains\Voicemail\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchVoicemailBoxGateway
{
    /** @param array<string, mixed> $voicemailBox
     * @return array<string, mixed>
     */
    public function create(SwitchAccount $account, array $voicemailBox): array;

    /** @param array<string, mixed> $voicemailBox
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $resourceId, array $voicemailBox): array;

    public function delete(SwitchAccount $account, string $resourceId): void;
}
