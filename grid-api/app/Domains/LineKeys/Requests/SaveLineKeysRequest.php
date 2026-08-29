<?php

namespace App\Domains\LineKeys\Requests;

use Illuminate\Foundation\Http\FormRequest;
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

                $identity = ($key['category'] ?? '').':'.($key['position'] ?? '');

                if (isset($seen[$identity])) {
                    $validator->errors()->add("line_keys.{$index}.position", 'Each category and position combination must be unique.');
                }

                $seen[$identity] = true;
                $value = $key['value'] ?? null;

                if ($value !== null && ! is_string($value) && ! is_int($value)) {
                    $validator->errors()->add("line_keys.{$index}.value", 'The value must be text or a number.');
                }

                if (is_string($value) && mb_strlen($value) > 255) {
                    $validator->errors()->add("line_keys.{$index}.value", 'The value must not exceed 255 characters.');
                }

                if (($key['label'] ?? null) !== null && $value === null) {
                    $validator->errors()->add("line_keys.{$index}.value", 'A labeled line key requires a value.');
                }

                if (($key['type'] ?? null) === 'parking' && $value !== null && (! is_numeric($value) || (int) $value < 1 || (int) $value > 10)) {
                    $validator->errors()->add("line_keys.{$index}.value", 'A parking position must be between 1 and 10.');
                }

                if (($key['type'] ?? null) !== 'parking' && is_int($value)) {
                    $validator->errors()->add("line_keys.{$index}.value", 'Non-parking line-key values must be text.');
                }
            }
        }];
    }
}
