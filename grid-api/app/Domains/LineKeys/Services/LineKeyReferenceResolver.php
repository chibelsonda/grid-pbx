<?php

namespace App\Domains\LineKeys\Services;

use App\Domains\LineKeys\Models\SwitchLineKey;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LineKeyReferenceResolver
{
    /**
     * @return list<array{id: string, source: string, types: list<string>, value: string, label: string, description: string|null}>
     */
    public function choices(SwitchAccount $account): array
    {
        $choices = [];

        foreach ($account->extensions()
            ->whereNotNull('switch_resource_id')
            ->orderBy('display_name')
            ->limit(250)
            ->get(['id', 'display_name', 'extension']) as $extension) {
            $label = is_string($extension->display_name) && trim($extension->display_name) !== ''
                ? $extension->display_name
                : ($extension->extension ?: 'Unnamed extension');

            $choices[] = [
                'id' => $extension->id,
                'source' => 'extensions',
                'types' => ['presence', 'personal_parking'],
                'value' => $extension->id,
                'label' => $label,
                'description' => $extension->extension,
            ];

            if (is_string($extension->extension) && trim($extension->extension) !== '') {
                $choices[] = [
                    'id' => $extension->id,
                    'source' => 'extensions',
                    'types' => ['speed_dial'],
                    'value' => $extension->extension,
                    'label' => $label,
                    'description' => $extension->extension,
                ];
            }
        }

        return $choices;
    }

    /**
     * @param  Collection<int, SwitchLineKey>  $keys
     */
    public function usePublicValues(SwitchAccount $account, Collection $keys): void
    {
        $references = $this->references($account, includeDeleted: true);

        foreach ($keys as $key) {
            if (in_array($key->type, ['presence', 'personal_parking'], true)
                && is_string($key->value)
                && isset($references['public_by_switch'][$key->value])) {
                $key->setAttribute('value', $references['public_by_switch'][$key->value]);
            }
        }
    }

    /**
     * @param  list<array{category: string, position: int, type: string, value: string|int|null, label: string|null}>  $keys
     * @return list<array{category: string, position: int, type: string, value: string|int|null, label: string|null}>
     */
    public function useSwitchValues(SwitchAccount $account, array $keys): array
    {
        $references = $this->references($account);
        $errors = [];

        foreach ($keys as $index => &$key) {
            $value = $key['value'] ?? null;

            if (! in_array($key['type'], ['presence', 'personal_parking'], true)) {
                if ($key['type'] === 'speed_dial' && is_string($value) && Str::isUuid($value)) {
                    $errors["line_keys.{$index}.value"][] = 'Enter a dialable destination, not a GridPBX resource identifier.';
                }

                continue;
            }

            if (! is_string($value) || ! Str::isUuid($value)) {
                $errors["line_keys.{$index}.value"][] = 'Select an extension from this account.';

                continue;
            }

            if (! isset($references['switch_by_public'][$value])) {
                $errors["line_keys.{$index}.value"][] = 'Select a reference from this account.';

                continue;
            }

            $key['value'] = $references['switch_by_public'][$value];
        }
        unset($key);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $keys;
    }

    /** @return array{switch_by_public: array<string, string>, public_by_switch: array<string, string>} */
    private function references(SwitchAccount $account, bool $includeDeleted = false): array
    {
        $switchByPublic = [];
        $publicBySwitch = [];
        $extensions = $account->extensions();

        if ($includeDeleted) {
            $extensions->withTrashed();
        }

        $resources = $extensions
            ->whereNotNull('switch_resource_id')
            ->get(['id', 'switch_resource_id']);

        foreach ($resources as $resource) {
            $switchByPublic[$resource->id] = $resource->switch_resource_id;
            $publicBySwitch[$resource->switch_resource_id] = $resource->id;
        }

        return [
            'switch_by_public' => $switchByPublic,
            'public_by_switch' => $publicBySwitch,
        ];
    }
}
