<?php

namespace App\Domains\Devices\Resources;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Media\Models\SwitchMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin SwitchDevice */
class DeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_type' => $this->device_type,
            'make' => $this->make,
            'endpoint_family' => $this->endpoint_family,
            'model' => $this->model,
            'mac_address' => $this->mac_address,
            'is_enabled' => $this->is_enabled,
            'registration_status' => $this->registration_status->value,
            'registration_checked_at' => $this->registration_checked_at?->toIso8601String(),
            'assigned_extension' => $this->extension === null ? null : [
                'id' => $this->extension->id,
                'display_name' => $this->extension->display_name,
                'extension' => $this->extension->extension,
            ],
            'configuration' => $this->configuration(),
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = is_array($this->switch_json) ? $this->switch_json : [];
        $sip = $this->object($snapshot, 'sip', [
            'method',
            'realm',
            'expire_seconds',
            'invite_format',
            'ip',
            'number',
            'route',
            'static_route',
            'ignore_completed_elsewhere',
        ]);
        $sip['custom_sip_headers'] = $this->customSipHeaders(Arr::get($snapshot, 'sip.custom_sip_headers'));
        $sip['username_configured'] = is_string(Arr::get($snapshot, 'sip.username'))
            && Arr::get($snapshot, 'sip.username') !== '';

        $musicOnHold = $this->musicOnHold(Arr::get($snapshot, 'music_on_hold.media_id'));

        return [
            'call_forward' => $this->object($snapshot, 'call_forward', [
                'enabled',
                'number',
                'direct_calls_only',
                'failover',
                'ignore_early_media',
                'keep_caller_id',
                'require_keypress',
                'substitute',
            ]),
            'sip' => $sip,
            'media' => [
                'audio' => ['codecs' => $this->stringList(Arr::get($snapshot, 'media.audio.codecs'))],
                'video' => ['codecs' => $this->stringList(Arr::get($snapshot, 'media.video.codecs'))],
                'bypass_media' => Arr::get($snapshot, 'media.bypass_media'),
                'encryption' => [
                    'enforce_security' => Arr::get($snapshot, 'media.encryption.enforce_security'),
                    'methods' => $this->stringList(Arr::get($snapshot, 'media.encryption.methods')),
                ],
                'fax_option' => Arr::get($snapshot, 'media.fax_option'),
                'ignore_early_media' => Arr::get($snapshot, 'media.ignore_early_media'),
                'progress_timeout' => Arr::get($snapshot, 'media.progress_timeout'),
            ],
            'caller_id' => [
                'internal' => $this->object($snapshot, 'caller_id.internal', ['name', 'number']),
                'external' => $this->object($snapshot, 'caller_id.external', ['name', 'number']),
                'emergency' => $this->object($snapshot, 'caller_id.emergency', ['name', 'number']),
                'asserted' => $this->object($snapshot, 'caller_id.asserted', ['name', 'number', 'realm']),
            ],
            'caller_id_options' => $this->object($snapshot, 'caller_id_options', ['outbound_privacy']),
            'call_waiting' => $this->object($snapshot, 'call_waiting', ['enabled']),
            'do_not_disturb' => $this->object($snapshot, 'do_not_disturb', ['enabled']),
            'contact_list' => $this->object($snapshot, 'contact_list', ['exclude']),
            'exclude_from_queues' => Arr::get($snapshot, 'exclude_from_queues'),
            'language' => Arr::get($snapshot, 'language'),
            'timezone' => Arr::get($snapshot, 'timezone'),
            'presence_id' => Arr::get($snapshot, 'presence_id'),
            'mwi_unsolicited_updates' => Arr::get($snapshot, 'mwi_unsolicited_updates'),
            'register_overwrite_notify' => Arr::get($snapshot, 'register_overwrite_notify'),
            'suppress_unregister_notifications' => Arr::get($snapshot, 'suppress_unregister_notifications'),
            'ringtones' => $this->object($snapshot, 'ringtones', ['internal', 'external']),
            'call_restriction' => $this->callRestrictions(Arr::get($snapshot, 'call_restriction')),
            'call_recording' => $this->callRecording(Arr::get($snapshot, 'call_recording')),
            'music_on_hold' => $musicOnHold,
            'outbound_flags' => $this->outboundFlags(Arr::get($snapshot, 'outbound_flags')),
            'dial_plan' => $this->dialPlan(Arr::get($snapshot, 'dial_plan')),
            'metaflows' => $this->metaflows(Arr::get($snapshot, 'metaflows')),
            'hotdesk' => [
                'active_user_count' => $this->associativeCount(Arr::get($snapshot, 'hotdesk.users')),
            ],
        ];
    }

    /** @return array{media_id: string|null, media_name: string|null} */
    private function musicOnHold(mixed $switchResourceId): array
    {
        if (! is_string($switchResourceId) || $switchResourceId === '') {
            return ['media_id' => null, 'media_name' => null];
        }

        $media = SwitchMedia::query()
            ->where('switch_account_id', $this->switch_account_id)
            ->where('switch_resource_id', $switchResourceId)
            ->first(['id', 'name']);

        return ['media_id' => $media?->id, 'media_name' => $media?->name];
    }

    /** @return array{static: list<string>, dynamic: list<string>} */
    private function outboundFlags(mixed $value): array
    {
        if (! is_array($value)) {
            return ['static' => [], 'dynamic' => []];
        }

        if (array_is_list($value)) {
            return ['static' => $this->stringList($value), 'dynamic' => []];
        }

        return [
            'static' => $this->stringList($value['static'] ?? null),
            'dynamic' => $this->stringList($value['dynamic'] ?? null),
        ];
    }

    /** @return array{in: list<array{name: string, value: string}>, out: list<array{name: string, value: string}>} */
    private function customSipHeaders(mixed $value): array
    {
        if (! is_array($value)) {
            return ['in' => [], 'out' => []];
        }

        $directional = array_key_exists('in', $value) || array_key_exists('out', $value);

        return [
            'in' => $this->headerRows($directional ? ($value['in'] ?? null) : null),
            'out' => $this->headerRows($directional ? ($value['out'] ?? null) : $value),
        ];
    }

    /** @return list<array{name: string, value: string}> */
    private function headerRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $name => $headerValue) {
            if (
                is_string($name)
                && is_string($headerValue)
                && preg_match('/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i', $name) !== 1
            ) {
                $rows[] = ['name' => $name, 'value' => $headerValue];
            }
        }

        return $rows;
    }

    /** @return array{system: list<string>, rules: list<array{pattern: string, description: string|null, prefix: string|null, suffix: string|null}>} */
    private function dialPlan(mixed $value): array
    {
        if (! is_array($value)) {
            return ['system' => [], 'rules' => []];
        }

        $rules = [];

        foreach ($value as $pattern => $settings) {
            if ($pattern === 'system' || ! is_string($pattern) || ! is_array($settings)) {
                continue;
            }

            $rules[] = [
                'pattern' => $pattern,
                'description' => $this->nullableString($settings['description'] ?? null),
                'prefix' => $this->nullableString($settings['prefix'] ?? null),
                'suffix' => $this->nullableString($settings['suffix'] ?? null),
            ];
        }

        return ['system' => $this->stringList($value['system'] ?? null), 'rules' => $rules];
    }

    /** @return array{binding_digit: string|null, digit_timeout: int|null, listen_on: string|null, number_flow_count: int, pattern_flow_count: int} */
    private function metaflows(mixed $value): array
    {
        $metaflows = is_array($value) ? $value : [];

        return [
            'binding_digit' => $this->nullableString($metaflows['binding_digit'] ?? null),
            'digit_timeout' => is_int($metaflows['digit_timeout'] ?? null) ? $metaflows['digit_timeout'] : null,
            'listen_on' => $this->nullableString($metaflows['listen_on'] ?? null),
            'number_flow_count' => $this->associativeCount($metaflows['numbers'] ?? null),
            'pattern_flow_count' => $this->associativeCount($metaflows['patterns'] ?? null),
        ];
    }

    private function associativeCount(mixed $value): int
    {
        return is_array($value) ? count($value) : 0;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function object(array $snapshot, string $path, array $keys): array
    {
        $value = Arr::get($snapshot, $path);

        return is_array($value) ? Arr::only($value, $keys) : [];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));
    }

    /** @return array<string, array{action: string}> */
    private function callRestrictions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $restrictions = [];

        foreach ($value as $classification => $settings) {
            if (! is_string($classification) || ! is_array($settings)) {
                continue;
            }

            $action = $settings['action'] ?? null;

            if (in_array($action, ['inherit', 'deny'], true)) {
                $restrictions[$classification] = ['action' => $action];
            }
        }

        return $restrictions;
    }

    /** @return array<string, array<string, array<string, bool|int|string>>> */
    private function callRecording(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $recording = [];
        $allowedParameters = [
            'enabled',
            'format',
            'record_min_sec',
            'record_on_answer',
            'record_on_bridge',
            'record_sample_rate',
            'time_limit',
        ];

        foreach (['any', 'inbound', 'outbound'] as $direction) {
            $source = $value[$direction] ?? null;

            if (! is_array($source)) {
                continue;
            }

            foreach (['any', 'onnet', 'offnet'] as $network) {
                $parameters = $source[$network] ?? null;

                if (is_array($parameters)) {
                    $recording[$direction][$network] = Arr::only($parameters, $allowedParameters);
                }
            }
        }

        return $recording;
    }
}
