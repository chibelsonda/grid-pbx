<?php

namespace App\Domains\LineKeys\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveLineKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'line_keys' => ['present', 'array', 'max:1000'],
            'line_keys.*' => ['array:category,position,type,value,label'],
            'line_keys.*.category' => ['required', Rule::in(['combo', 'feature'])],
            'line_keys.*.position' => ['required', 'integer', 'min:0', 'max:999'],
            'line_keys.*.type' => ['required', Rule::in(['line', 'presence', 'personal_parking', 'speed_dial', 'parking'])],
            'line_keys.*.value' => ['nullable'],
            'line_keys.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];

            foreach ((array) $this->input('line_keys', []) as $index => $key) {
                if (! is_array($key)) {
                    continue;
                }

                $identity = (string) ($key['position'] ?? '');

                if (isset($seen[$identity])) {
                    $validator->errors()->add("line_keys.{$index}.position", 'Each physical model position may be assigned only once.');
                }

                $seen[$identity] = true;
                $value = $key['value'] ?? null;

                if ($value !== null && ! is_string($value) && ! is_int($value)) {
                    $validator->errors()->add("line_keys.{$index}.value", 'The value must be text or a number.');
                }

                if (is_string($value) && mb_strlen($value) > 255) {
                    $validator->errors()->add("line_keys.{$index}.value", 'The value must not exceed 255 characters.');
                }

                $type = $key['type'] ?? null;
                $category = $key['category'] ?? null;

                if ($type === 'line') {
                    if ($category !== 'combo') {
                        $validator->errors()->add("line_keys.{$index}.category", 'Line appearances must use combo keys.');
                    }

                    if ($value !== null) {
                        $validator->errors()->add("line_keys.{$index}.value", 'Line appearances do not accept a value.');
                    }

                    if (($key['label'] ?? null) !== null) {
                        $validator->errors()->add("line_keys.{$index}.label", 'Line appearances do not accept a label.');
                    }

                    continue;
                }

                if (($key['label'] ?? null) !== null && $value === null) {
                    $validator->errors()->add("line_keys.{$index}.value", 'A labeled line key requires a value.');
                }

                if ($value === null || $value === '') {
                    $validator->errors()->add("line_keys.{$index}.value", 'The selected line-key type requires a value.');

                    continue;
                }

                if (in_array($type, ['presence', 'personal_parking'], true)
                    && (! is_string($value) || ! Str::isUuid($value))) {
                    $validator->errors()->add("line_keys.{$index}.value", 'Select an extension from this account.');
                }

                if ($type === 'speed_dial' && (! is_string($value) || trim($value) === '')) {
                    $validator->errors()->add("line_keys.{$index}.value", 'Enter a dialable destination.');
                }

                if ($type === 'parking') {
                    $parkingPosition = filter_var($value, FILTER_VALIDATE_INT);

                    if ($parkingPosition === false || $parkingPosition < 1 || $parkingPosition > 10) {
                        $validator->errors()->add("line_keys.{$index}.value", 'A parking position must be between 1 and 10.');
                    }
                }

                if ($type !== 'parking' && is_int($value)) {
                    $validator->errors()->add("line_keys.{$index}.value", 'Non-parking line-key values must be text.');
                }
            }
        }];
    }
}
