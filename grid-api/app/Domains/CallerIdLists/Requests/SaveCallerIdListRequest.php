<?php

namespace App\Domains\CallerIdLists\Requests;

use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveCallerIdListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:128'],
            'description' => ['present', 'nullable', 'string', 'min:1', 'max:128'],
            'organization' => ['present', 'nullable', 'string', 'max:255'],
            'entries' => ['present', 'array', 'max:10000'],
            'entries.*' => ['required', 'array:id,display_name,number,pattern'],
            'entries.*.id' => ['present', 'nullable', 'uuid', 'distinct'],
            'entries.*.display_name' => ['present', 'nullable', 'string', 'min:1', 'max:128'],
            'entries.*.number' => ['present', 'nullable', 'string', 'regex:/^\+?[0-9]{1,32}$/'],
            'entries.*.pattern' => ['present', 'nullable', 'string', 'max:512', new SafeSwitchRegex],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('entries', []) as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $number = $entry['number'] ?? null;
                $pattern = $entry['pattern'] ?? null;

                if (($number === null) === ($pattern === null)) {
                    $validator->errors()->add(
                        "entries.{$index}.number",
                        'Enter either a caller number/prefix or a match pattern, but not both.',
                    );
                }
            }
        }];
    }
}
