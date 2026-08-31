<?php

namespace App\Domains\Organizations\Resources;

use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Switch\MetaflowPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin SwitchAccount */
class AccountDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = is_string($this->organization_role)
            ? OrganizationRole::tryFrom($this->organization_role)
            : null;
        $snapshot = is_array($this->switch_json) ? $this->switch_json : [];
        $callerId = is_array($snapshot['caller_id'] ?? null) ? $snapshot['caller_id'] : [];
        $callerIdOptions = is_array($snapshot['caller_id_options'] ?? null)
            ? $snapshot['caller_id_options']
            : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'realm' => $this->realm,
            'timezone' => $this->timezone,
            'enabled' => $this->is_enabled,
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ],
            'resource_counts' => [
                'extensions' => $this->extensions_count,
                'devices' => $this->devices_count,
                'phone_numbers' => $this->phone_numbers_count,
                'callflows' => $this->callflows_count,
                'voicemail_boxes' => $this->voicemail_boxes_count,
                'queues' => $this->queues_count,
                'media' => $this->media_count,
                'recordings' => $this->recordings_count,
            ],
            'configuration' => [
                'organization_name' => $this->org_name,
                'language' => $this->language,
                'call_waiting_enabled' => $this->call_waiting_enabled ?? true,
                'do_not_disturb_enabled' => $this->do_not_disturb_enabled ?? false,
                'outbound_privacy' => $this->outbound_privacy,
                'show_rate' => ($callerIdOptions['show_rate'] ?? false) === true,
                'ringtone_internal' => $this->ringtone_internal,
                'ringtone_external' => $this->ringtone_external,
                'caller_id' => [
                    'internal' => $this->callerIdText($callerId, 'internal'),
                    'external' => $this->callerIdNumber($callerId, 'external'),
                    'emergency' => $this->callerIdNumber($callerId, 'emergency'),
                ],
                'call_restriction' => $this->callRestrictions($snapshot['call_restriction'] ?? null),
                'call_recording' => $this->callRecording($snapshot['call_recording'] ?? null),
                'dial_plan' => $this->dialPlan($snapshot['dial_plan'] ?? null),
                'formatters' => $this->formatters($snapshot['formatters'] ?? null),
                'preflow' => $this->preflow($snapshot['preflow'] ?? null),
                'metaflows' => $this->metaflows($snapshot['metaflows'] ?? null),
            ],
            'options' => [
                'caller_id_numbers' => $this->phoneNumbers
                    ->map(fn ($phoneNumber): array => [
                        'id' => $phoneNumber->id,
                        'number' => $phoneNumber->number,
                        'display_name' => $phoneNumber->cnam_display_name,
                        'e911_enabled' => $phoneNumber->isE911Enabled(),
                    ])
                    ->values()
                    ->all(),
            ],
            'projection' => [
                'status' => $this->sync_status,
                'version' => $this->projection_version,
                'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            ],
            'permissions' => [
                'can_manage_settings' => $role?->canManageAccountSettings() ?? false,
            ],
            'configuration_boundaries' => [
                'identity_defaults' => 'safe_fields_available',
                'calling_defaults' => 'safe_fields_available',
                'advanced_routing' => 'guided_rules_available',
                'enable_disable' => 'implemented_confirmed',
                'billing_topup' => 'provider_required',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $callerId
     * @return array{name: ?string, number: ?string}
     */
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
            : $this->phoneNumbers->firstWhere('number', $value['number']);

        return [
            'name' => $value['name'],
            'phone_number_id' => $phoneNumber?->id,
            'number' => $value['number'],
            'unresolved' => $value['number'] !== null && $phoneNumber === null,
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, array{action: string}> */
    private function callRestrictions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $restrictions = [];

        foreach ($value as $classification => $settings) {
            $action = is_array($settings) ? ($settings['action'] ?? null) : null;

            if (is_string($classification) && in_array($action, ['inherit', 'deny'], true)) {
                $restrictions[$classification] = ['action' => $action];
            }
        }

        return $restrictions;
    }

    /** @return array<string, array<string, array<string, array<string, mixed>>>> */
    private function callRecording(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

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

        foreach (['account', 'endpoint'] as $target) {
            foreach (['any', 'inbound', 'outbound'] as $direction) {
                foreach (['any', 'onnet', 'offnet'] as $network) {
                    $parameters = data_get($value, "{$target}.{$direction}.{$network}");

                    if (is_array($parameters)) {
                        $recording[$target][$direction][$network] = Arr::only($parameters, $editable);
                    }
                }
            }
        }

        return $recording;
    }

    /** @return array{system: list<string>, rules: list<array<string, ?string>>} */
    private function dialPlan(mixed $value): array
    {
        if (! is_array($value)) {
            return ['system' => [], 'rules' => []];
        }

        $system = array_values(array_filter(
            is_array($value['system'] ?? null) ? $value['system'] : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
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
    private function formatters(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

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

    /** @return array{callflow_id: ?string, name: ?string, unresolved: bool} */
    private function preflow(mixed $value): array
    {
        $switchId = is_array($value) ? $this->stringValue($value['always'] ?? null) : null;
        $callflow = $switchId === null
            ? null
            : $this->callflows->firstWhere('switch_resource_id', $switchId);

        return [
            'callflow_id' => $callflow?->id,
            'name' => $callflow?->name,
            'unresolved' => $switchId !== null && $callflow === null,
        ];
    }

    /**
     * @return array{
     *   binding_digit: ?string,
     *   digit_timeout: ?int,
     *   listen_on: ?string,
     *   number_flow_count: int,
     *   pattern_flow_count: int,
     *   actions: list<array<string, mixed>>,
     *   locked_action_count: int
     * }
     */
    private function metaflows(mixed $value): array
    {
        $metaflows = is_array($value) ? $value : [];
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
            'actions' => $policy->editableActions($metaflows, $this->resource),
            'locked_action_count' => $policy->lockedActionCount($metaflows, $this->resource),
        ];
    }
}
