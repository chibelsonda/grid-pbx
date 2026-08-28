<?php

namespace App\Domains\Blacklists\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBlacklistsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['search' => ['nullable', 'string', 'max:128'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]; }
}
