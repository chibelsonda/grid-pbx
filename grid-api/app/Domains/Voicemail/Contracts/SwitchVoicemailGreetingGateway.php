<?php

namespace App\Domains\Voicemail\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Http\BinaryResponse;
use Psr\Http\Message\StreamInterface;

interface SwitchVoicemailGreetingGateway
{
    /** @return array<string, mixed> */
    public function create(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        string $name,
        string $description,
    ): array;

    /** @return array<string, mixed> */
    public function upload(
        SwitchAccount $account,
        string $mediaResourceId,
        StreamInterface $stream,
        string $contentType,
        int $contentLength,
    ): array;

    /** @return array<string, mixed> */
    public function assign(
        SwitchAccount $account,
        string $voicemailBoxResourceId,
        ?string $mediaResourceId,
    ): array;

    public function audio(SwitchAccount $account, string $mediaResourceId, ?string $range = null): BinaryResponse;

    public function delete(SwitchAccount $account, string $mediaResourceId): void;
}
