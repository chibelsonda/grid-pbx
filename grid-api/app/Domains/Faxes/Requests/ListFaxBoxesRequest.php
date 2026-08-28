<?php

namespace App\Domains\Faxes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListFaxBoxesRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['search' => ['nullable', 'string', 'max:128'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]; }
}
