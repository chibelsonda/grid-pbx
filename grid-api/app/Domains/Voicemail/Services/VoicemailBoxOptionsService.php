<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeZone;
use GridPbx\Switch\Shared\Capabilities\CapabilityProvider;
use GridPbx\Switch\Shared\Exceptions\SwitchAuthenticationException;

class VoicemailBoxOptionsService
{
    public function __construct(private readonly CapabilityProvider $capabilities) {}

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account): array
    {
        $transcription = $this->transcriptionCapability();

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
                    'runtime_available' => $transcription['available'],
                    'default_enabled' => $transcription['default'],
                ],
            ],
        ];
    }

    /** @return array{available: bool|null, default: bool|null} */
    private function transcriptionCapability(): array
    {
        try {
            return $this->capabilities->capability('voicemail.transcription');
        } catch (SwitchAuthenticationException) {
            return ['available' => null, 'default' => null];
        }
    }
}
