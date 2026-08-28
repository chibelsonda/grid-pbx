<?php

namespace App\Domains\Blacklists\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveBlacklistRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['required', 'string', 'max:128'], 'should_block_anonymous' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'], 'numbers' => ['present', 'array', 'max:10000'], 'numbers.*' => ['required', 'string', 'distinct', 'regex:/^\+[1-9]\d{6,14}$/']]; }
}
