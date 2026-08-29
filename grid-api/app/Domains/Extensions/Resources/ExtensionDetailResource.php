<?php

namespace App\Domains\Extensions\Resources;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Shared\Switch\MetaflowPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchExtension */
class ExtensionDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $callerId = is_array($this->switch_json['caller_id'] ?? null)
            ? $this->switch_json['caller_id']
            : [];

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'extension' => $this->extension,
            'timezone' => $this->timezone,
            'is_enabled' => $this->is_enabled,
            'is_managed' => $this->is_managed,
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'configuration' => [
                'language' => $this->safeString('language'),
                'presence_id' => $this->safeString('presence_id'),
                'call_waiting' => [
                    'enabled' => $this->safeBoolean(['call_waiting', 'enabled'], true),
                ],
                'do_not_disturb' => [
                    'enabled' => $this->safeBoolean(['do_not_disturb', 'enabled'], false),
                ],
                'contact_list' => [
                    'exclude' => $this->safeBoolean(['contact_list', 'exclude'], false),
                ],
                'caller_id_options' => [
                    'outbound_privacy' => $this->safeOutboundPrivacy(),
                ],
                'caller_id' => [
                    'internal' => $this->callerIdText($callerId, 'internal'),
                    'external' => $this->callerIdNumber($callerId, 'external'),
                    'emergency' => $this->callerIdNumber($callerId, 'emergency'),
                ],
                'call_forward' => $this->callForward(),
                'call_restriction' => $this->callRestrictions(),
                'call_recording' => $this->callRecording(),
                'media' => $this->media(),
                'music_on_hold' => $this->musicOnHold(),
                'ringtones' => [
                    'internal' => $this->safeNestedString(['ringtones', 'internal']),
                    'external' => $this->safeNestedString(['ringtones', 'external']),
                ],
                'dial_plan' => $this->dialPlan(),
                'formatters' => $this->formatters(),
                'profile' => $this->profile(),
                'pronounced_name' => $this->pronouncedName(),
                'policy' => [
                    'verified' => $this->safeBoolean(['verified'], false),
                    'privilege' => in_array($this->switch_json['priv_level'] ?? null, ['user', 'admin'], true)
                        ? $this->switch_json['priv_level']
                        : null,
                    'feature_level' => $this->safeString('feature_level'),
                    'external_flag_count' => is_array($this->switch_json['flags'] ?? null)
                        ? count($this->switch_json['flags'])
                        : 0,
                ],
                'credentials' => [
                    'password_configured' => is_string($this->username) && $this->username !== '',
                    'require_password_update' => $this->safeBoolean(
                        ['require_password_update'],
                        false,
                    ),
                ],
                'hotdesk' => [
                    'enabled' => $this->safeBoolean(['hotdesk', 'enabled'], false),
                    'id' => $this->safeNestedString(['hotdesk', 'id']),
                    'keep_logged_in_elsewhere' => $this->safeBoolean(
                        ['hotdesk', 'keep_logged_in_elsewhere'],
                        false,
                    ),
                    'require_pin' => $this->safeBoolean(['hotdesk', 'require_pin'], false),
                    'pin_configured' => $this->safeNestedString(['hotdesk', 'pin']) !== null,
                ],
                'metaflows' => $this->metaflows(),
            ],
            'devices' => $this->devices->map(fn ($device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'make' => $device->make,
                'model' => $device->model,
                'mac_address' => $device->mac_address,
                'is_enabled' => $device->is_enabled,
                'is_managed' => $device->is_managed,
                'sync_status' => $device->sync_status->value,
                'last_synced_at' => $device->last_synced_at?->toIso8601String(),
            ])->all(),
            'voicemail_boxes' => $this->voicemailBoxes->map(fn ($voicemailBox): array => [
                'id' => $voicemailBox->id,
                'name' => $voicemailBox->name,
                'mailbox' => $voicemailBox->mailbox,
                'is_setup' => $voicemailBox->is_setup,
                'timezone' => $voicemailBox->timezone,
                'notification_emails' => $voicemailBox->notification_emails ?? [],
                'transcribe' => $voicemailBox->transcribe,
                'require_pin' => $voicemailBox->require_pin,
                'message_count' => (int) ($voicemailBox->messages_count ?? 0),
                'is_managed' => $voicemailBox->is_managed,
                'sync_status' => $voicemailBox->sync_status->value,
                'last_synced_at' => $voicemailBox->last_synced_at?->toIso8601String(),
            ])->all(),
            'callflows' => $this->callflows->map(fn ($callflow): array => [
                'id' => $callflow->id,
                'name' => $callflow->name,
                'numbers' => $callflow->numbers,
                'modules' => $callflow->modules,
                'is_managed' => $callflow->is_managed,
                'sync_status' => $callflow->sync_status->value,
                'last_synced_at' => $callflow->last_synced_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function safeString(string $key): ?string
    {
        $value = $this->switch_json[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param list<string> $path */
    private function safeNestedString(array $path): ?string
    {
        $value = $this->switch_json;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param list<string> $path */
    private function safeBoolean(array $path, bool $default): bool
    {
        $value = $this->switch_json;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return is_bool($value) ? $value : $default;
    }

    private function safeOutboundPrivacy(): string
    {
        $value = $this->switch_json['caller_id_options']['outbound_privacy'] ?? null;

        return in_array($value, ['full', 'name', 'number', 'none'], true) ? $value : 'none';
    }

    /** @param array<string, mixed> $callerId @return array{name: ?string, number: ?string} */
    private function callerIdText(array $callerId, string $scope): array
    {
        $value = is_array($callerId[$scope] ?? null) ? $callerId[$scope] : [];

        return [
            'name' => $this->stringValue($value['name'] ?? null),
            'number' => $this->stringValue($value['number'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $callerId
     * @return array{name: ?string, phone_number_id: ?string, number: ?string, unresolved: bool}
     */
    private function callerIdNumber(array $callerId, string $scope): array
    {
        $value = $this->callerIdText($callerId, $scope);
        $phoneNumber = $value['number'] === null
            ? null
            : $this->switchAccount->phoneNumbers->firstWhere('number', $value['number']);

        return [
            'name' => $value['name'],
            'phone_number_id' => $phoneNumber?->id,
            'number' => $value['number'],
            'unresolved' => $value['number'] !== null && $phoneNumber === null,
        ];
    }

    /** @return array<string, bool|string|null> */
    private function callForward(): array
    {
        $value = is_array($this->switch_json['call_forward'] ?? null)
            ? $this->switch_json['call_forward']
            : [];

        return [
            'enabled' => ($value['enabled'] ?? false) === true,
            'number' => $this->stringValue($value['number'] ?? null),
            'direct_calls_only' => ($value['direct_calls_only'] ?? false) === true,
            'failover' => ($value['failover'] ?? false) === true,
            'ignore_early_media' => ($value['ignore_early_media'] ?? true) === true,
            'keep_caller_id' => ($value['keep_caller_id'] ?? true) === true,
            'require_keypress' => ($value['require_keypress'] ?? true) === true,
            'substitute' => ($value['substitute'] ?? true) === true,
        ];
    }

    /** @return array<string, array{action: string}> */
    private function callRestrictions(): array
    {
        $value = is_array($this->switch_json['call_restriction'] ?? null)
            ? $this->switch_json['call_restriction']
            : [];
        $restrictions = [];

        foreach ($value as $classification => $settings) {
            $action = is_array($settings) ? ($settings['action'] ?? null) : null;

            if (is_string($classification) && in_array($action, ['inherit', 'deny'], true)) {
                $restrictions[$classification] = ['action' => $action];
            }
        }

        return $restrictions;
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function callRecording(): array
    {
        $value = is_array($this->switch_json['call_recording'] ?? null)
            ? $this->switch_json['call_recording']
            : [];
        $recording = [];
        $editable = [
            'enabled',
            'format',
            'record_min_sec',
            'record_on_answer',
            'record_on_bridge',
            'record_sample_rate',
            'time_limit',
        ];

        foreach (['any', 'inbound', 'outbound'] as $direction) {
            foreach (['any', 'onnet', 'offnet'] as $network) {
                $parameters = data_get($value, "{$direction}.{$network}");

                if (is_array($parameters)) {
                    $recording[$direction][$network] = array_intersect_key(
                        $parameters,
                        array_flip($editable),
                    );
                }
            }
        }

        return $recording;
    }

    /** @return array<string, mixed> */
    private function media(): array
    {
        $value = is_array($this->switch_json['media'] ?? null)
            ? $this->switch_json['media']
            : [];
        $bypassMedia = $value['bypass_media'] ?? false;

        return [
            'audio' => [
                'codecs' => $this->safeStringList(data_get($value, 'audio.codecs')),
            ],
            'video' => [
                'codecs' => $this->safeStringList(data_get($value, 'video.codecs')),
            ],
            'bypass_media' => in_array($bypassMedia, [true, false, 'auto'], true)
                ? $bypassMedia
                : false,
            'encryption' => [
                'enforce_security' => data_get($value, 'encryption.enforce_security') === true,
                'methods' => array_values(array_intersect(
                    $this->safeStringList(data_get($value, 'encryption.methods')),
                    ['srtp', 'zrtp'],
                )),
            ],
            'fax_option' => ($value['fax_option'] ?? false) === true,
            'ignore_early_media' => ($value['ignore_early_media'] ?? false) === true,
            'progress_timeout' => is_int($value['progress_timeout'] ?? null)
                ? $value['progress_timeout']
                : null,
        ];
    }

    /** @return array{media_id: ?string, configured: bool, unresolved: bool} */
    private function musicOnHold(): array
    {
        $resourceId = $this->safeNestedString(['music_on_hold', 'media_id']);
        $media = $resourceId === null
            ? null
            : $this->switchAccount->media->firstWhere('switch_resource_id', $resourceId);

        return [
            'media_id' => $media?->id,
            'configured' => $resourceId !== null,
            'unresolved' => $resourceId !== null && $media === null,
        ];
    }

    /** @return array{system: list<string>, rules: list<array<string, ?string>>} */
    private function dialPlan(): array
    {
        $value = is_array($this->switch_json['dial_plan'] ?? null)
            ? $this->switch_json['dial_plan']
            : [];
        $system = $this->safeStringList($value['system'] ?? null);
        $rules = [];

        foreach ($value as $pattern => $options) {
            if ($pattern === 'system' || ! is_string($pattern) || ! is_array($options)) {
                continue;
            }

            $rules[] = [
                'pattern' => $pattern,
                'description' => $this->stringValue($options['description'] ?? null),
                'prefix' => $this->stringValue($options['prefix'] ?? null),
                'suffix' => $this->stringValue($options['suffix'] ?? null),
            ];
        }

        return ['system' => $system, 'rules' => $rules];
    }

    /** @return list<array<string, bool|string|null>> */
    private function formatters(): array
    {
        $value = is_array($this->switch_json['formatters'] ?? null)
            ? $this->switch_json['formatters']
            : [];
        $formatters = [];

        foreach ($value as $field => $stored) {
            if (! is_string($field) || ! is_array($stored)) {
                continue;
            }

            $rules = array_is_list($stored) ? $stored : [$stored];

            foreach ($rules as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $formatters[] = [
                    'field' => $field,
                    'direction' => in_array($rule['direction'] ?? null, ['inbound', 'outbound', 'both'], true)
                        ? $rule['direction']
                        : null,
                    'match_invite_format' => ($rule['match_invite_format'] ?? false) === true,
                    'prefix' => $this->stringValue($rule['prefix'] ?? null),
                    'regex' => $this->stringValue($rule['regex'] ?? null),
                    'strip' => ($rule['strip'] ?? false) === true,
                    'suffix' => $this->stringValue($rule['suffix'] ?? null),
                    'value' => $this->stringValue($rule['value'] ?? null),
                ];
            }
        }

        return $formatters;
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        $value = is_array($this->switch_json['profile'] ?? null)
            ? $this->switch_json['profile']
            : [];
        $allowedTypes = ['dom', 'postal', 'intl', 'parcel', 'home', 'work', 'pref'];
        $addresses = [];

        foreach (is_array($value['addresses'] ?? null) ? $value['addresses'] : [] as $address) {
            if (! is_array($address) || $this->stringValue($address['address'] ?? null) === null) {
                continue;
            }

            $addresses[] = [
                'address' => $address['address'],
                'types' => array_values(array_intersect(
                    $this->safeStringList($address['types'] ?? null),
                    $allowedTypes,
                )),
            ];
        }

        return [
            'addresses' => $addresses,
            'assistant' => $this->stringValue($value['assistant'] ?? null),
            'birthday' => $this->stringValue($value['birthday'] ?? null),
            'nicknames' => $this->safeStringList($value['nicknames'] ?? null),
            'note' => $this->stringValue($value['note'] ?? null),
            'role' => $this->stringValue($value['role'] ?? null),
            'sort_string' => $this->stringValue($value['sort-string'] ?? null),
            'title' => $this->stringValue($value['title'] ?? null),
        ];
    }

    /** @return array{media_id: ?string, configured: bool, unresolved: bool} */
    private function pronouncedName(): array
    {
        $resourceId = $this->safeNestedString(['pronounced_name', 'media_id']);
        $media = $resourceId === null
            ? null
            : $this->switchAccount->media->firstWhere('switch_resource_id', $resourceId);

        return [
            'media_id' => $media?->id,
            'configured' => $resourceId !== null,
            'unresolved' => $resourceId !== null && $media === null,
        ];
    }

    /** @return list<string> */
    private function safeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /** @return array<string, mixed> */
    private function metaflows(): array
    {
        $metaflows = is_array($this->switch_json['metaflows'] ?? null)
            ? $this->switch_json['metaflows']
            : [];
        $policy = app(MetaflowPolicy::class);

        return [
            'binding_digit' => $this->stringValue($metaflows['binding_digit'] ?? null),
            'digit_timeout' => is_int($metaflows['digit_timeout'] ?? null)
                ? $metaflows['digit_timeout']
                : null,
            'listen_on' => in_array($metaflows['listen_on'] ?? null, ['both', 'self', 'peer'], true)
                ? $metaflows['listen_on']
                : null,
            'number_flow_count' => is_array($metaflows['numbers'] ?? null)
                ? count($metaflows['numbers'])
                : 0,
            'pattern_flow_count' => is_array($metaflows['patterns'] ?? null)
                ? count($metaflows['patterns'])
                : 0,
            'actions' => $policy->editableActions($metaflows, $this->switchAccount),
            'locked_action_count' => $policy->lockedActionCount($metaflows, $this->switchAccount),
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
