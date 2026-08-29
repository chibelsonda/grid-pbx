<?php

namespace App\Domains\Devices\Requests;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Rules\UniqueDeviceMacAddress;
use App\Domains\Devices\Services\DeviceMetaflowPolicy;
use App\Domains\Devices\Services\DeviceSchemaCompatibilityService;
use App\Domains\Devices\Services\ProvisioningCatalogSelectionService;
use App\Domains\Devices\Support\MacAddress;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveDeviceRequest extends FormRequest
{
    private ?ProvisioningCatalogSelectionService $catalogSelections = null;

    /** @var list<string> */
    private const DEVICE_TYPES = [
        'sip_device',
        'cellphone',
        'smartphone',
        'softphone',
        'landline',
        'fax',
        'ata',
        'sip_uri',
    ];

    /** @var list<string> */
    private const AUDIO_CODECS = [
        'OPUS',
        'CELT@32000h',
        'G7221@32000h',
        'G7221@16000h',
        'G722',
        'speex@32000h',
        'speex@16000h',
        'PCMU',
        'PCMA',
        'G729',
        'GSM',
        'CELT@48000h',
        'CELT@64000h',
        'G722_16',
        'G722_32',
        'CELT_48',
        'CELT_64',
        'Speex',
        'speex',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(
        DeviceSchemaCompatibilityService $schemaCompatibility,
        ProvisioningCatalogSelectionService $catalogSelections,
    ): array {
        $this->catalogSelections = $catalogSelections;
        $compatibility = $schemaCompatibility->current();
        $deviceType = $this->string('device_type')->toString();
        $isSipUri = $deviceType === 'sip_uri';
        $isForwardingOnly = in_array($deviceType, ['cellphone', 'landline'], true);
        $isMinimalWorkflow = $isSipUri || $isForwardingOnly;
        $callForwardNumberMaximum = (int) data_get($compatibility, 'call_forward.number_max_length', 15);
        $inviteFormats = array_values(array_filter(
            (array) data_get($compatibility, 'sip.invite_formats', []),
            static fn (mixed $format): bool => is_string($format),
        ));
        $inviteFormats = $inviteFormats === []
            ? ['username', 'npan', '1npan', 'e164', 'route', 'contact']
            : $inviteFormats;
        $sipKeys = $isSipUri ? ['invite_format', 'route'] : [
            'method', 'username', 'password', 'realm', 'expire_seconds', 'invite_format', 'ip',
            'number', 'route', 'static_route', 'ignore_completed_elsewhere', 'custom_sip_headers',
        ];
        $provisionKeys = ['endpoint_brand', 'endpoint_family', 'endpoint_model'];

        foreach ($isSipUri ? [] : ['custom_sip_interface', 'forward', 'proxy', 'static_invite', 'transport'] as $field) {
            if (data_get($compatibility, "sip.{$field}") === true) {
                $sipKeys[] = $field;
            }
        }

        if (data_get($compatibility, 'provision.template_id') === true) {
            $provisionKeys[] = 'id';
        }

        foreach (['check_sync_event', 'check_sync_reload', 'check_sync_reboot'] as $field) {
            if (data_get($compatibility, "provision.{$field}") === true) {
                $provisionKeys[] = $field;
            }
        }

        $endpointModelTypes = (array) data_get(
            $compatibility,
            'provision.endpoint_model_types',
            ['string'],
        );

        $rules = [
            'name' => ['required', 'string', 'max:128'],
            'device_type' => ['required', 'string', Rule::in(self::DEVICE_TYPES)],
            'make' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'string', 'max:255'],
            'model' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'string', 'max:255'],
            'mac_address' => [
                Rule::prohibitedIf($isMinimalWorkflow),
                'nullable',
                'string',
                'max:64',
                'regex:/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/',
                new UniqueDeviceMacAddress(
                    $this->accountInternalId(),
                    is_string($this->route('device')) ? $this->route('device') : null,
                ),
            ],
            'is_enabled' => ['required', 'boolean'],
            'assigned_extension_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('switch_extensions', 'id')
                    ->where('switch_account_id', $this->accountInternalId()),
            ],
            'sip_username' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'string', 'min:2', 'max:32'],
            'sip_password' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'string', 'min:12', 'max:32'],
            'provision' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:'.implode(',', $provisionKeys)],
            'provision.endpoint_brand' => ['nullable', 'string', 'max:255'],
            'provision.endpoint_family' => ['nullable', 'string', 'max:255'],
            'provision.endpoint_model' => [
                'nullable',
                function (string $attribute, mixed $value, callable $fail) use ($endpointModelTypes): void {
                    $validString = is_string($value)
                        && in_array('string', $endpointModelTypes, true)
                        && mb_strlen($value) <= 255;
                    $validInteger = is_int($value)
                        && in_array('integer', $endpointModelTypes, true);
                    $validArray = is_array($value)
                        && in_array('array', $endpointModelTypes, true)
                        && count($value) <= 32
                        && collect($value)->every(
                            static fn (mixed $model): bool => is_string($model)
                                && trim($model) !== ''
                                && mb_strlen($model) <= 255,
                        );

                    if (! $validString && ! $validInteger && ! $validArray) {
                        $fail('The endpoint model does not match the connected Switch schema.');
                    }
                },
            ],
            'provision.id' => ['nullable', 'string', 'max:255'],
            'provision.check_sync_event' => ['nullable', 'string', 'max:255'],
            'provision.check_sync_reload' => ['nullable', 'string', 'max:255'],
            'provision.check_sync_reboot' => ['nullable', 'string', 'max:255'],
            'call_forward' => [
                Rule::requiredIf($isForwardingOnly),
                Rule::prohibitedIf($isSipUri),
                'array:enabled,number,direct_calls_only,failover,ignore_early_media,keep_caller_id,require_keypress,substitute',
            ],
            'call_forward.enabled' => ['sometimes', 'boolean'],
            'call_forward.number' => ['nullable', 'string', "max:{$callForwardNumberMaximum}"],
            'call_forward.direct_calls_only' => ['sometimes', 'boolean'],
            'call_forward.failover' => ['sometimes', 'boolean'],
            'call_forward.ignore_early_media' => ['sometimes', 'boolean'],
            'call_forward.keep_caller_id' => ['sometimes', 'boolean'],
            'call_forward.require_keypress' => ['sometimes', 'boolean'],
            'call_forward.substitute' => ['sometimes', 'boolean'],
            'sip' => [
                Rule::requiredIf($isSipUri),
                Rule::prohibitedIf($isForwardingOnly),
                'array:'.implode(',', $sipKeys),
            ],
            'sip.method' => ['sometimes', 'string', Rule::in(['password', 'ip'])],
            'sip.username' => ['nullable', 'string', 'min:2', 'max:32'],
            'sip.password' => ['nullable', 'string', 'min:12', 'max:32'],
            'sip.realm' => ['nullable', 'string', 'min:4', 'max:253', 'regex:/^[.\w_-]+$/'],
            'sip.expire_seconds' => ['nullable', 'integer', 'min:30', 'max:86400'],
            'sip.invite_format' => [
                Rule::requiredIf($isSipUri),
                'string',
                Rule::in($inviteFormats),
            ],
            'sip.ip' => ['nullable', 'ip'],
            'sip.number' => ['nullable', 'string', 'max:64'],
            'sip.route' => [Rule::requiredIf($isSipUri), 'nullable', 'string', 'max:2048'],
            'sip.static_route' => ['nullable', 'string', 'max:2048'],
            'sip.custom_sip_interface' => ['nullable', 'string', 'max:255'],
            'sip.forward' => ['nullable', 'string', 'max:255'],
            'sip.proxy' => ['nullable', 'string', 'max:2048'],
            'sip.static_invite' => ['nullable', 'string', 'max:2048'],
            'sip.transport' => ['nullable', 'string', 'max:32'],
            'sip.ignore_completed_elsewhere' => ['sometimes', 'boolean'],
            'sip.custom_sip_headers' => ['sometimes', 'array:in,out'],
            'sip.custom_sip_headers.in' => ['sometimes', 'array', 'max:50'],
            'sip.custom_sip_headers.out' => ['sometimes', 'array', 'max:50'],
            'sip.custom_sip_headers.in.*' => ['array:name,value'],
            'sip.custom_sip_headers.out.*' => ['array:name,value'],
            'sip.custom_sip_headers.in.*.name' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/', 'not_regex:/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i'],
            'sip.custom_sip_headers.out.*.name' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/', 'not_regex:/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i'],
            'sip.custom_sip_headers.in.*.value' => ['required', 'string', 'max:1024'],
            'sip.custom_sip_headers.out.*.value' => ['required', 'string', 'max:1024'],
            'media' => [
                'sometimes',
                Rule::prohibitedIf($isMinimalWorkflow),
                'array:audio,video,bypass_media,encryption,fax_option,ignore_early_media,progress_timeout',
            ],
            'media.audio' => ['sometimes', 'array:codecs'],
            'media.audio.codecs' => ['sometimes', 'array', 'max:19'],
            'media.audio.codecs.*' => ['string', 'distinct:strict', Rule::in(self::AUDIO_CODECS)],
            'media.video' => ['sometimes', 'array:codecs'],
            'media.video.codecs' => ['sometimes', 'array', 'max:4'],
            'media.video.codecs.*' => [
                'string',
                'distinct:strict',
                Rule::in(['H261', 'H263', 'H264', 'VP8']),
            ],
            'media.bypass_media' => ['nullable', Rule::in([true, false, 'auto'])],
            'media.encryption' => ['sometimes', 'array:enforce_security,methods'],
            'media.encryption.enforce_security' => ['sometimes', 'boolean'],
            'media.encryption.methods' => ['sometimes', 'array', 'max:2'],
            'media.encryption.methods.*' => [
                'string',
                'distinct:strict',
                Rule::in(['zrtp', 'srtp']),
            ],
            'media.fax_option' => ['sometimes', 'boolean'],
            'media.ignore_early_media' => ['sometimes', 'boolean'],
            'media.progress_timeout' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'caller_id' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:internal,external,emergency,asserted'],
            'caller_id.internal' => ['sometimes', 'array:name,number'],
            'caller_id.internal.name' => ['nullable', 'string', 'max:35'],
            'caller_id.internal.number' => ['nullable', 'string', 'max:35'],
            'caller_id.external' => ['sometimes', 'array:name,number'],
            'caller_id.external.name' => ['nullable', 'string', 'max:35'],
            'caller_id.external.number' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency' => ['sometimes', 'array:name,number'],
            'caller_id.emergency.name' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency.number' => ['nullable', 'string', 'max:35'],
            'caller_id.asserted' => ['sometimes', 'array:name,number,realm'],
            'caller_id.asserted.name' => ['nullable', 'string', 'max:35'],
            'caller_id.asserted.number' => ['nullable', 'string', 'max:35'],
            'caller_id.asserted.realm' => ['nullable', 'string', 'max:253'],
            'caller_id_options' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:outbound_privacy'],
            'caller_id_options.outbound_privacy' => [
                'nullable',
                'string',
                Rule::in(['full', 'name', 'number', 'none']),
            ],
            'call_waiting' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:enabled'],
            'call_waiting.enabled' => ['sometimes', 'boolean'],
            'do_not_disturb' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:enabled'],
            'do_not_disturb.enabled' => ['sometimes', 'boolean'],
            'contact_list' => ['sometimes', 'array:exclude'],
            'contact_list.exclude' => ['sometimes', 'boolean'],
            'exclude_from_queues' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'boolean'],
            'language' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'string', 'max:32'],
            'timezone' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'timezone'],
            'presence_id' => [Rule::prohibitedIf($isMinimalWorkflow), 'nullable', 'string', 'max:255'],
            'mwi_unsolicited_updates' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'boolean'],
            'register_overwrite_notify' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'boolean'],
            'suppress_unregister_notifications' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'boolean'],
            'ringtones' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:internal,external'],
            'ringtones.internal' => ['nullable', 'string', 'max:256'],
            'ringtones.external' => ['nullable', 'string', 'max:256'],
            'call_restriction' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array', 'max:100'],
            'call_restriction.*' => ['array:action'],
            'call_restriction.*.action' => ['required', 'string', Rule::in(['inherit', 'deny'])],
            'call_recording' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:any,inbound,outbound'],
            'music_on_hold' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:media_id'],
            'music_on_hold.media_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('switch_media', 'id')
                    ->where('switch_account_id', $this->accountInternalId()),
            ],
            'outbound_flags' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:static,dynamic'],
            'outbound_flags.static' => ['sometimes', 'array', 'max:64'],
            'outbound_flags.dynamic' => ['sometimes', 'array', 'max:64'],
            'outbound_flags.static.*' => ['string', 'distinct:strict', 'max:255'],
            'outbound_flags.dynamic.*' => ['string', 'distinct:strict', 'max:255'],
            'dial_plan' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:system,rules'],
            'dial_plan.system' => ['sometimes', 'array', 'max:64'],
            'dial_plan.system.*' => ['string', 'distinct:strict', 'max:255'],
            'dial_plan.rules' => ['sometimes', 'array', 'max:64'],
            'dial_plan.rules.*' => ['array:pattern,description,prefix,suffix'],
            'dial_plan.rules.*.pattern' => ['required', 'string', 'distinct:strict', 'max:512'],
            'dial_plan.rules.*.description' => ['nullable', 'string', 'max:255'],
            'dial_plan.rules.*.prefix' => ['nullable', 'string', 'max:64'],
            'dial_plan.rules.*.suffix' => ['nullable', 'string', 'max:64'],
            'metaflows' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array:binding_digit,digit_timeout,listen_on,actions'],
            'metaflows.binding_digit' => ['nullable', 'string', Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#'])],
            'metaflows.digit_timeout' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'metaflows.listen_on' => ['nullable', 'string', Rule::in(['both', 'self', 'peer'])],
            'metaflows.actions' => ['sometimes', 'array', 'max:50'],
            'metaflows.actions.*' => ['array:trigger_type,trigger,module,data,children'],
            'metaflows.actions.*.trigger_type' => ['required', 'string', Rule::in(['number', 'pattern'])],
            'metaflows.actions.*.trigger' => ['required', 'string', 'max:255'],
            'metaflows.actions.*.module' => ['required', 'string', Rule::in(array_keys(DeviceMetaflowPolicy::EDITABLE_MODULE_FIELDS))],
            'metaflows.actions.*.data' => ['present', 'array', 'max:20'],
            'metaflows.actions.*.children' => ['sometimes', 'array', 'max:20'],
            'flags' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array', 'max:64'],
            'flags.*' => ['string', 'distinct:strict', 'max:255'],
            'formatters' => ['sometimes', Rule::prohibitedIf($isMinimalWorkflow), 'array', 'max:64'],
            'formatters.*' => ['array:field,direction,match_invite_format,prefix,regex,strip,suffix,value'],
            'formatters.*.field' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'formatters.*.direction' => ['nullable', 'string', Rule::in(['inbound', 'outbound', 'both'])],
            'formatters.*.match_invite_format' => ['sometimes', 'boolean'],
            'formatters.*.prefix' => ['nullable', 'string', 'max:1024'],
            'formatters.*.regex' => ['nullable', 'string', 'max:2048'],
            'formatters.*.strip' => ['sometimes', 'boolean'],
            'formatters.*.suffix' => ['nullable', 'string', 'max:1024'],
            'formatters.*.value' => ['nullable', 'string', 'max:1024'],
        ];

        foreach (['any', 'inbound', 'outbound'] as $direction) {
            $rules["call_recording.{$direction}"] = ['sometimes', 'array:any,onnet,offnet'];

            foreach (['any', 'onnet', 'offnet'] as $network) {
                $path = "call_recording.{$direction}.{$network}";
                $rules[$path] = [
                    'sometimes',
                    'array:enabled,format,record_min_sec,record_on_answer,record_on_bridge,record_sample_rate,time_limit',
                ];
                $rules["{$path}.enabled"] = ['sometimes', 'boolean'];
                $rules["{$path}.format"] = ['sometimes', 'string', Rule::in(['mp3', 'wav'])];
                $rules["{$path}.record_min_sec"] = ['nullable', 'integer', 'min:0', 'max:3600'];
                $rules["{$path}.record_on_answer"] = ['sometimes', 'boolean'];
                $rules["{$path}.record_on_bridge"] = ['sometimes', 'boolean'];
                $rules["{$path}.record_sample_rate"] = [
                    'nullable',
                    'integer',
                    Rule::in([8000, 16000, 32000, 48000]),
                ];
                $rules["{$path}.time_limit"] = ['nullable', 'integer', 'min:5', 'max:10800'];
            }
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateForwardingWorkflow($validator);
            $this->validateProvisioningSelection($validator);
            $this->validateProjectedCallerIdNumber($validator, 'external', false);
            $this->validateProjectedCallerIdNumber($validator, 'emergency', true);
            $this->validateMetaflowActions($validator);
        }];
    }

    protected function prepareForValidation(): void
    {
        $macAddress = $this->input('mac_address');

        if (! is_string($macAddress)) {
            return;
        }

        $this->merge(['mac_address' => MacAddress::canonicalize($macAddress)]);
    }

    private function validateProvisioningSelection(Validator $validator): void
    {
        if (! in_array($this->input('device_type'), ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'], true)) {
            return;
        }

        $values = [
            'provision.endpoint_brand' => $this->input('provision.endpoint_brand'),
            'provision.endpoint_family' => $this->input('provision.endpoint_family'),
            'provision.endpoint_model' => $this->input('provision.endpoint_model'),
        ];
        $configured = collect($values)->filter(static function (mixed $value): bool {
            return is_array($value) ? $value !== [] : trim((string) $value) !== '';
        });

        if ($configured->isEmpty()) {
            return;
        }

        foreach ($values as $path => $value) {
            if ((is_array($value) && $value === []) || (! is_array($value) && trim((string) $value) === '')) {
                $validator->errors()->add($path, 'Select the complete provisioning brand, family, and model.');
            }
        }

        if (count($configured) === count($values) && $this->catalogSelections !== null) {
            foreach ($this->catalogSelections->errors(
                $values['provision.endpoint_brand'],
                $values['provision.endpoint_family'],
                $values['provision.endpoint_model'],
                $this->input('provision.id'),
            ) as $path => $message) {
                $validator->errors()->add($path, $message);
            }
        }

        if (trim((string) $this->input('mac_address')) === '') {
            $validator->errors()->add('mac_address', 'Enter the MAC address used by the provisioner.');
        }
    }

    private function validateForwardingWorkflow(Validator $validator): void
    {
        if (! in_array($this->input('device_type'), ['cellphone', 'landline'], true)) {
            return;
        }

        $enabled = $this->input('is_enabled');
        $forwardingEnabled = $this->input('call_forward.enabled');

        if (is_bool($enabled) && is_bool($forwardingEnabled) && $enabled !== $forwardingEnabled) {
            $validator->errors()->add(
                'call_forward.enabled',
                'Forwarding state must match the device enabled state.',
            );
        }

        if ($forwardingEnabled === true && trim((string) $this->input('call_forward.number')) === '') {
            $validator->errors()->add(
                'call_forward.number',
                'Enter the number that should receive forwarded calls.',
            );
        }
    }

    private function validateMetaflowActions(Validator $validator): void
    {
        $seen = [];
        $nodeCount = 0;
        $resourceIds = $this->metaflowResourceIds();

        foreach ((array) $this->input('metaflows.actions', []) as $index => $action) {
            if (! is_array($action)) {
                continue;
            }

            $identity = ($action['trigger_type'] ?? '').':'.($action['trigger'] ?? '');

            if (($action['trigger_type'] ?? null) === 'number'
                && is_string($action['trigger'] ?? null)
                && preg_match('/^[0-9]+$/', $action['trigger']) !== 1) {
                $validator->errors()->add("metaflows.actions.{$index}.trigger", 'Number metaflow triggers may contain digits only.');
            }

            if (isset($seen[$identity])) {
                $validator->errors()->add("metaflows.actions.{$index}.trigger", 'Each metaflow trigger must be unique within its type.');
            }
            $seen[$identity] = true;

            $this->validateMetaflowNode(
                $validator,
                $action,
                "metaflows.actions.{$index}",
                0,
                $nodeCount,
                $resourceIds,
            );
        }

        if ($nodeCount > 100) {
            $validator->errors()->add('metaflows.actions', 'A device may contain at most 100 guided metaflow nodes.');
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, true>>  $resourceIds
     */
    private function validateMetaflowNode(
        Validator $validator,
        array $node,
        string $path,
        int $depth,
        int &$nodeCount,
        array $resourceIds,
    ): void {
        $nodeCount++;

        if ($depth > 8) {
            $validator->errors()->add($path.'.children', 'Metaflow branches may be at most 8 levels deep.');

            return;
        }

        $module = is_string($node['module'] ?? null) ? $node['module'] : '';
        $allowed = DeviceMetaflowPolicy::EDITABLE_MODULE_FIELDS[$module] ?? [];

        foreach ((array) ($node['data'] ?? []) as $field => $value) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                $validator->errors()->add($path.'.data', 'The selected metaflow module contains an unsupported field.');

                continue;
            }

            if (! is_null($value) && ! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                $validator->errors()->add("{$path}.data.{$field}", 'Metaflow values must be text, numbers, booleans, or null.');
            }

            if (is_string($value) && strlen($value) > 2048) {
                $validator->errors()->add("{$path}.data.{$field}", 'Metaflow text values must not exceed 2048 characters.');
            }
        }

        foreach ($this->metaflowResourceFields($module) as $field => $resource) {
            if (! array_key_exists($field, (array) ($node['data'] ?? []))) {
                continue;
            }

            $id = $node['data'][$field];

            if (! is_string($id) || ! Str::isUuid($id) || ! isset($resourceIds[$resource][$id])) {
                $validator->errors()->add(
                    "{$path}.data.{$field}",
                    'Select a projected resource from this account.',
                );
            }
        }

        if ($module === 'play' && ! isset($node['data']['media_id'])) {
            $validator->errors()->add($path.'.data.media_id', 'Select media to play.');
        }

        if ($module === 'callflow' && ! isset($node['data']['callflow_id'])) {
            $validator->errors()->add($path.'.data.callflow_id', 'Select a callflow to run.');
        }

        if ($module === 'move'
            && ! isset($node['data']['device_id'])
            && ! isset($node['data']['extension_id'])) {
            $validator->errors()->add($path.'.data', 'Select a destination device or extension.');
        }

        $seenKeys = [];

        foreach ((array) ($node['children'] ?? []) as $childIndex => $child) {
            $childPath = "{$path}.children.{$childIndex}";

            if (! is_array($child)) {
                $validator->errors()->add($childPath, 'Each metaflow child must be an object.');

                continue;
            }

            $key = $child['key'] ?? null;

            if (! is_string($key) || trim($key) === '' || strlen($key) > 64) {
                $validator->errors()->add($childPath.'.key', 'Enter a branch key up to 64 characters.');
            } elseif (isset($seenKeys[$key])) {
                $validator->errors()->add($childPath.'.key', 'Branch keys must be unique at this level.');
            } else {
                $seenKeys[$key] = true;
            }

            if (! is_string($child['module'] ?? null)
                || ! array_key_exists($child['module'], DeviceMetaflowPolicy::EDITABLE_MODULE_FIELDS)) {
                $validator->errors()->add($childPath.'.module', 'Select a supported metaflow action.');
            }

            $this->validateMetaflowNode(
                $validator,
                $child,
                $childPath,
                $depth + 1,
                $nodeCount,
                $resourceIds,
            );
        }
    }

    /** @return array<string, string> */
    private function metaflowResourceFields(string $module): array
    {
        return match ($module) {
            'play' => ['media_id' => 'media'],
            'callflow' => ['callflow_id' => 'callflow'],
            'move' => [
                'device_id' => 'device',
                'extension_id' => 'extension',
            ],
            default => [],
        };
    }

    /** @return array<string, array<string, true>> */
    private function metaflowResourceIds(): array
    {
        $accountId = $this->accountInternalId();

        if ($accountId === null) {
            return ['media' => [], 'callflow' => [], 'device' => [], 'extension' => []];
        }

        $resources = [];

        foreach ([
            'media' => 'switch_media',
            'callflow' => 'switch_callflows',
            'device' => 'switch_devices',
            'extension' => 'switch_extensions',
        ] as $resource => $table) {
            $resources[$resource] = DB::table($table)
                ->where('switch_account_id', $accountId)
                ->pluck('id')
                ->mapWithKeys(static fn (string $id): array => [$id => true])
                ->all();
        }

        return $resources;
    }

    private function validateProjectedCallerIdNumber(
        Validator $validator,
        string $scope,
        bool $requiresE911,
    ): void {
        $number = $this->input("caller_id.{$scope}.number");

        if (! is_string($number) || trim($number) === '') {
            return;
        }

        $number = trim($number);

        if ($this->isCurrentCallerIdNumber($scope, $number)) {
            return;
        }

        $accountId = $this->accountInternalId();
        $phoneNumber = $accountId === null
            ? null
            : SwitchPhoneNumber::query()
                ->where('switch_account_id', $accountId)
                ->where('number', $number)
                ->first();

        if ($phoneNumber === null) {
            $validator->errors()->add(
                "caller_id.{$scope}.number",
                'Select a phone number assigned to this account.',
            );

            return;
        }

        if ($requiresE911 && ! $phoneNumber->isE911Enabled()) {
            $validator->errors()->add(
                "caller_id.{$scope}.number",
                'Select a phone number with E911 enabled.',
            );
        }
    }

    private function isCurrentCallerIdNumber(string $scope, string $number): bool
    {
        $deviceId = $this->route('device');
        $accountId = $this->accountInternalId();

        if (! is_string($deviceId) || $accountId === null) {
            return false;
        }

        $device = SwitchDevice::query()
            ->where('switch_account_id', $accountId)
            ->where('id', $deviceId)
            ->first(['switch_json']);

        $current = data_get($device?->switch_json, "caller_id.{$scope}.number");

        return is_string($current) && hash_equals($current, $number);
    }

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }
}
