<?php

namespace App\Domains\GlobalSearch\Services;

use App\Domains\GlobalSearch\Enums\GlobalSearchType;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class GlobalSearchService
{
    private const int RESULT_LIMIT = 5;

    private const int CANDIDATE_LIMIT = 25;

    /**
     * @param  array<int, GlobalSearchType>  $types
     * @return array{query: string, groups: array<int, array{type: string, label: string, results: array<int, array<string, string>>}>, total: int}
     */
    public function search(SwitchAccount $account, string $query, array $types): array
    {
        $normalizedQuery = $this->normalize($query);
        $groups = [];
        $total = 0;

        foreach ($types as $type) {
            $results = $this->searchType($account, $type, $normalizedQuery);

            if ($results === []) {
                continue;
            }

            $groups[] = [
                'type' => $type->value,
                'label' => $type->groupLabel(),
                'results' => $results,
            ];
            $total += count($results);
        }

        return [
            'query' => $query,
            'groups' => $groups,
            'total' => $total,
        ];
    }

    /** @return array<int, array<string, string>> */
    private function searchType(
        SwitchAccount $account,
        GlobalSearchType $type,
        string $query,
    ): array {
        $model = $type->modelClass();
        $fields = match ($type) {
            GlobalSearchType::Extension => [
                'display_name', 'first_name', 'last_name', 'extension', 'username', 'email',
            ],
            GlobalSearchType::Device => [
                'name', 'device_type', 'make', 'endpoint_family', 'model', 'mac_address',
            ],
            GlobalSearchType::PhoneNumber => [
                'number', 'cnam_display_name', 'carrier_name', 'state',
            ],
            GlobalSearchType::Callflow => ['name', 'numbers'],
            GlobalSearchType::VoicemailBox => ['name', 'mailbox'],
            GlobalSearchType::Queue => ['name', 'strategy'],
            GlobalSearchType::Menu => ['name'],
            GlobalSearchType::Conference => ['name', 'profile_name', 'language'],
            GlobalSearchType::Directory => ['name', 'sort_by'],
            GlobalSearchType::Group => ['name'],
            GlobalSearchType::Media => [
                'name', 'description', 'language', 'media_source',
            ],
            GlobalSearchType::Recording => [
                'name', 'caller_id_name', 'caller_id_number', 'callee_id_name',
                'callee_id_number', 'call_id', 'interaction_id',
            ],
            GlobalSearchType::FaxBox => [
                'name', 'smtp_email_address', 'custom_smtp_email_address', 'caller_id',
            ],
            GlobalSearchType::Blacklist => ['name'],
            GlobalSearchType::CallerIdList => [
                'name', 'description', 'organization',
            ],
        };

        /** @var Builder<Model> $builder */
        $builder = $model::query();
        $columns = array_values(array_unique([
            'id',
            'switch_account_id',
            ...$fields,
            ...$this->presentationFields($type),
        ]));
        $grammar = $builder->getQuery()->getGrammar();
        $wrappedFields = array_map(
            static fn (string $field): string => $grammar->wrap($field),
            $fields,
        );
        $escapedQuery = $this->escapeLike($query);
        $exactConditions = implode(' OR ', array_map(
            static fn (string $field): string => "LOWER({$field}) = ?",
            $wrappedFields,
        ));
        $prefixConditions = implode(' OR ', array_map(
            static fn (string $field): string => "LOWER({$field}) LIKE ? ESCAPE '!'",
            $wrappedFields,
        ));
        $rankBindings = [
            ...array_fill(0, count($fields), $query),
            ...array_fill(0, count($fields), "{$escapedQuery}%"),
        ];

        /** @var Collection<int, Model> $candidates */
        $candidates = $builder
            ->select($columns)
            ->where('switch_account_id', $account->getKey())
            ->where(function (Builder $search) use ($wrappedFields, $escapedQuery): void {
                foreach ($wrappedFields as $index => $field) {
                    if ($index === 0) {
                        $search->whereRaw("{$field} LIKE ? ESCAPE '!'", ["%{$escapedQuery}%"]);

                        continue;
                    }

                    $search->orWhereRaw("{$field} LIKE ? ESCAPE '!'", ["%{$escapedQuery}%"]);
                }
            })
            // Rank before limiting so an exact result cannot be hidden behind broad contains matches.
            ->orderByRaw(
                "CASE WHEN ({$exactConditions}) THEN 0 WHEN ({$prefixConditions}) THEN 1 ELSE 2 END",
                $rankBindings,
            )
            ->orderBy($fields[0])
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return $candidates
            ->map(fn (Model $model): array => $this->result($type, $model, $fields, $query))
            ->sortBy([
                ['rank', 'asc'],
                ['title_sort', 'asc'],
                ['id', 'asc'],
            ])
            ->take(self::RESULT_LIMIT)
            ->map(static function (array $result): array {
                unset($result['rank'], $result['title_sort']);

                return $result;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, int|string>
     */
    private function result(
        GlobalSearchType $type,
        Model $model,
        array $fields,
        string $query,
    ): array {
        $values = collect($fields)->mapWithKeys(function (string $field) use ($model): array {
            $value = $model->getAttribute($field);

            if (is_array($value)) {
                $value = implode(' ', array_filter($value, 'is_scalar'));
            }

            return [$field => is_scalar($value) ? (string) $value : ''];
        });
        $matchedField = (string) ($values->keys()->first(
            fn (string $field): bool => str_contains($this->normalize($values->get($field, '')), $query),
        ) ?? $fields[0]);
        [$title, $subtitle] = $this->presentation($type, $model);

        return [
            'id' => (string) $model->getAttribute('id'),
            'type' => $type->value,
            'title' => $title,
            'subtitle' => $subtitle,
            'matched_field' => $matchedField,
            'rank' => $this->rank($values->all(), $query),
            'title_sort' => $this->normalize($title),
        ];
    }

    /** @return array{string, string} */
    private function presentation(GlobalSearchType $type, Model $model): array
    {
        return match ($type) {
            GlobalSearchType::Extension => [
                $this->firstValue($model, ['display_name', 'username'], 'Unnamed extension'),
                $this->joinValues($model, ['extension', 'email']),
            ],
            GlobalSearchType::Device => [
                $this->firstValue($model, ['name'], 'Unnamed device'),
                $this->joinValues($model, ['device_type', 'make', 'model', 'mac_address']),
            ],
            GlobalSearchType::PhoneNumber => [
                $this->firstValue($model, ['number'], 'Unassigned number'),
                $this->joinValues($model, ['cnam_display_name', 'state', 'carrier_name']),
            ],
            GlobalSearchType::Callflow => [
                $this->firstValue($model, ['name'], 'Unnamed route'),
                $this->arraySummary($model->getAttribute('numbers')),
            ],
            GlobalSearchType::VoicemailBox => [
                $this->firstValue($model, ['name'], 'Unnamed mailbox'),
                $this->prefixedValue($model, 'mailbox', 'Mailbox '),
            ],
            GlobalSearchType::Queue => [
                $this->firstValue($model, ['name'], 'Unnamed queue'),
                $this->prefixedValue($model, 'strategy', 'Strategy: '),
            ],
            GlobalSearchType::Menu => [
                $this->firstValue($model, ['name'], 'Unnamed menu'),
                'IVR menu',
            ],
            GlobalSearchType::Conference => [
                $this->firstValue($model, ['name'], 'Unnamed conference'),
                $this->joinValues($model, ['profile_name', 'language']),
            ],
            GlobalSearchType::Directory => [
                $this->firstValue($model, ['name'], 'Unnamed directory'),
                'Directory',
            ],
            GlobalSearchType::Group => [
                $this->firstValue($model, ['name'], 'Unnamed group'),
                'Group or ring group',
            ],
            GlobalSearchType::Media => [
                $this->firstValue($model, ['name'], 'Unnamed media'),
                $this->joinValues($model, ['media_source', 'language', 'description']),
            ],
            GlobalSearchType::Recording => [
                $this->firstValue(
                    $model,
                    ['name', 'caller_id_name', 'caller_id_number', 'call_id'],
                    'Unnamed recording',
                ),
                $this->joinValues($model, [
                    'direction', 'caller_id_number', 'callee_id_number',
                ]),
            ],
            GlobalSearchType::FaxBox => [
                $this->firstValue($model, ['name'], 'Unnamed fax box'),
                $this->joinValues($model, [
                    'smtp_email_address', 'custom_smtp_email_address', 'caller_id',
                ]),
            ],
            GlobalSearchType::Blacklist => [
                $this->firstValue($model, ['name'], 'Unnamed blacklist'),
                $model->getAttribute('is_active') ? 'Active blacklist' : 'Inactive blacklist',
            ],
            GlobalSearchType::CallerIdList => [
                $this->firstValue($model, ['name'], 'Unnamed Caller-ID List'),
                $this->joinValues($model, ['organization', 'description']),
            ],
        };
    }

    /** @return array<int, string> */
    private function presentationFields(GlobalSearchType $type): array
    {
        return match ($type) {
            GlobalSearchType::Recording => ['direction'],
            GlobalSearchType::Blacklist => ['is_active'],
            default => [],
        };
    }

    /** @param array<string, string> $values */
    private function rank(array $values, string $query): int
    {
        $normalized = array_values(array_filter(array_map($this->normalize(...), $values)));

        if (in_array($query, $normalized, true)) {
            return 0;
        }

        if (collect($normalized)->contains(fn (string $value): bool => str_starts_with($value, $query))) {
            return 1;
        }

        return 2;
    }

    /** @param array<int, string> $fields */
    private function firstValue(Model $model, array $fields, string $fallback): string
    {
        foreach ($fields as $field) {
            $value = trim((string) $model->getAttribute($field));

            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    /** @param array<int, string> $fields */
    private function joinValues(Model $model, array $fields): string
    {
        return collect($fields)
            ->map(fn (string $field): string => trim((string) $model->getAttribute($field)))
            ->filter()
            ->unique()
            ->implode(' · ');
    }

    private function prefixedValue(Model $model, string $field, string $prefix): string
    {
        $value = trim((string) $model->getAttribute($field));

        return $value === '' ? '' : $prefix.$value;
    }

    private function arraySummary(mixed $value): string
    {
        return is_array($value)
            ? collect($value)
                ->filter(static fn (mixed $item): bool => is_scalar($item))
                ->map(static fn (mixed $item): string => (string) $item)
                ->implode(' · ')
            : '';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
