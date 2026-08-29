<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeZone;

class VoicemailBoxOptionsService
{
    /** @return array<string, mixed> */
    public function get(SwitchAccount $account): array
    {
        return [
            'account_defaults' => ['timezone' => $account->timezone],
            'timezones' => DateTimeZone::listIdentifiers(),
            'extensions' => $account->extensions()
                ->orderBy('display_name')
                ->orderBy('extension_id')
                ->get(['id', 'display_name', 'extension'])
                ->map(static fn ($extension): array => [
                    'id' => $extension->id,
                    'display_name' => $extension->display_name,
                    'extension' => $extension->extension,
                ])
                ->all(),
            'capabilities' => [
                'voicemail_transcription' => [
                    'schema_supported' => true,
                    'runtime_available' => null,
                    'default_enabled' => null,
                ],
            ],
        ];
    }
}
