<?php

namespace App\Domains\GlobalSearch\Requests;

use App\Domains\GlobalSearch\Enums\GlobalSearchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['sometimes', 'array', 'max:15'],
            'types.*' => ['string', Rule::enum(GlobalSearchType::class)],
        ];
    }

    public function queryText(): string
    {
        return trim((string) $this->validated('q'));
    }

    /** @return array<int, GlobalSearchType> */
    public function searchTypes(): array
    {
        $types = $this->validated('types');

        if (! is_array($types) || $types === []) {
            return GlobalSearchType::cases();
        }

        return collect($types)
            ->filter(static fn (mixed $type): bool => is_string($type))
            ->unique()
            ->map(static fn (string $type): GlobalSearchType => GlobalSearchType::from($type))
            ->values()
            ->all();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->merge(['q' => trim($this->query('q'))]);
        }
    }
}
