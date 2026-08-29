<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Switch\MetaflowInputValidator;
use App\Shared\Switch\MetaflowPolicy;
use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountSettingsRequest extends FormRequest
{
    private bool $accountResolved = false;

    private ?SwitchAccount $resolvedAccount = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:128'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:32'],
            'call_waiting_enabled' => ['required', 'boolean'],
            'do_not_disturb_enabled' => ['required', 'boolean'],
            'outbound_privacy' => ['required', Rule::in(['full', 'name', 'number', 'none'])],
            'show_rate' => ['required', 'boolean'],
            'ringtone_internal' => ['nullable', 'string', 'max:255'],
            'ringtone_external' => ['nullable', 'string', 'max:255'],
            'caller_id' => ['required', 'array:internal,external,emergency'],
            'caller_id.internal' => ['required', 'array:name,number'],
            'caller_id.internal.name' => ['nullable', 'string', 'max:35'],
            'caller_id.internal.number' => ['nullable', 'string', 'max:35'],
            'caller_id.external' => ['required', 'array:name,phone_number_id,preserve_number'],
            'caller_id.external.name' => ['nullable', 'string', 'max:35'],
            'caller_id.external.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.external.preserve_number' => ['required', 'boolean'],
            'caller_id.emergency' => ['required', 'array:name,phone_number_id,preserve_number'],
            'caller_id.emergency.name' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.emergency.preserve_number' => ['required', 'boolean'],
            'call_restriction' => ['sometimes', 'array', 'max:100'],
            'call_restriction.*' => ['array:action'],
            'call_restriction.*.action' => ['required', Rule::in(['inherit', 'deny'])],
            'call_recording' => ['sometimes', 'array:account,endpoint'],
            'dial_plan' => ['sometimes', 'array:system,rules'],
            'dial_plan.system' => ['sometimes', 'array', 'max:64'],
            'dial_plan.system.*' => ['string', 'distinct:strict', 'max:255'],
            'dial_plan.rules' => ['sometimes', 'array', 'max:64'],
            'dial_plan.rules.*' => ['array:pattern,description,prefix,suffix,preserved_options'],
            'dial_plan.rules.*.pattern' => ['required', 'string', 'max:512', new SafeSwitchRegex],
            'dial_plan.rules.*.description' => ['nullable', 'string', 'max:255'],
            'dial_plan.rules.*.prefix' => ['nullable', 'string', 'max:64'],
            'dial_plan.rules.*.suffix' => ['nullable', 'string', 'max:64'],
            'dial_plan.rules.*.preserved_options' => ['prohibited'],
            'formatters' => ['sometimes', 'array', 'max:64'],
            'formatters.*' => [
                'array:field,direction,match_invite_format,prefix,regex,strip,suffix,value,preserved_options',
            ],
            'formatters.*.field' => [
                'required',
                'string',
                'max:128',
                'regex:/^[A-Za-z0-9_]+$/',
            ],
            'formatters.*.direction' => ['nullable', Rule::in(['inbound', 'outbound', 'both'])],
            'formatters.*.match_invite_format' => ['required', 'boolean'],
            'formatters.*.prefix' => ['nullable', 'string', 'max:1024'],
            'formatters.*.regex' => ['nullable', 'string', 'max:2048', new SafeSwitchRegex],
            'formatters.*.strip' => ['required', 'boolean'],
            'formatters.*.suffix' => ['nullable', 'string', 'max:1024'],
            'formatters.*.value' => ['nullable', 'string', 'max:1024'],
            'formatters.*.preserved_options' => ['prohibited'],
            'preflow' => ['sometimes', 'array:callflow_id,preserve_callflow'],
            'preflow.callflow_id' => [
                'nullable',
                'uuid',
                Rule::exists('switch_callflows', 'id')
                    ->where('switch_account_id', $this->accountInternalId()),
            ],
            'preflow.preserve_callflow' => ['required_with:preflow', 'boolean'],
            'metaflows' => ['sometimes', 'array:binding_digit,digit_timeout,listen_on,actions,preserved_options'],
            'metaflows.binding_digit' => [
                'nullable',
                'string',
                Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#']),
            ],
            'metaflows.digit_timeout' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'metaflows.listen_on' => ['nullable', Rule::in(['both', 'self', 'peer'])],
            'metaflows.actions' => ['sometimes', 'array', 'max:50'],
            'metaflows.actions.*' => ['array:trigger_type,trigger,module,data,children'],
            'metaflows.actions.*.trigger_type' => ['required', Rule::in(['number', 'pattern'])],
            'metaflows.actions.*.trigger' => ['required', 'string', 'max:255'],
            'metaflows.actions.*.module' => [
                'required',
                Rule::in(array_keys(MetaflowPolicy::EDITABLE_MODULE_FIELDS)),
            ],
            'metaflows.actions.*.data' => ['present', 'array', 'max:20'],
            'metaflows.actions.*.children' => ['sometimes', 'array', 'max:20'],
            'metaflows.preserved_options' => ['prohibited'],
        ];

        foreach (['account', 'endpoint'] as $target) {
            foreach (['any', 'inbound', 'outbound'] as $direction) {
                $rules["call_recording.{$target}.{$direction}"] = ['sometimes', 'array:any,onnet,offnet'];

                foreach (['any', 'onnet', 'offnet'] as $network) {
                    $path = "call_recording.{$target}.{$direction}.{$network}";
                    $rules[$path] = [
                        'sometimes',
                        'array:enabled,format,record_min_sec,record_on_answer,record_on_bridge,record_sample_rate,time_limit',
                    ];
                    $rules["{$path}.enabled"] = ['sometimes', 'boolean'];
                    $rules["{$path}.format"] = ['sometimes', Rule::in(['mp3', 'wav'])];
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
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $restrictions = $this->input('call_restriction', []);

            if (is_array($restrictions)) {
                foreach (array_keys($restrictions) as $classification) {
                    if (! is_string($classification)
                        || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $classification) !== 1) {
                        $validator->errors()->add(
                            'call_restriction',
                            'A call restriction contains an invalid classification key.',
                        );

                        break;
                    }
                }
            }

            $patterns = collect($this->input('dial_plan.rules', []))
                ->filter(fn (mixed $rule): bool => is_array($rule) && is_string($rule['pattern'] ?? null))
                ->pluck('pattern');

            if ($patterns->count() !== $patterns->uniqueStrict()->count()) {
                $validator->errors()->add('dial_plan.rules', 'Dial-plan patterns must be unique.');
            }

            $account = $this->accountModel();

            if ($account !== null) {
                app(MetaflowInputValidator::class)->validate(
                    $validator,
                    (array) $this->input('metaflows.actions', []),
                    $account,
                );
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter an account name.',
            'outbound_privacy.required' => 'Select an outbound privacy policy.',
            'outbound_privacy.in' => 'Select a valid outbound privacy policy.',
        ];
    }

    private function accountInternalId(): ?string
    {
        return $this->accountModel()?->getKey();
    }

    private function accountModel(): ?SwitchAccount
    {
        if ($this->accountResolved) {
            return $this->resolvedAccount;
        }

        $this->accountResolved = true;
        $publicId = $this->route('account');

        return $this->resolvedAccount = is_string($publicId)
            ? SwitchAccount::query()->where('id', $publicId)->first()
            : null;
    }
}
