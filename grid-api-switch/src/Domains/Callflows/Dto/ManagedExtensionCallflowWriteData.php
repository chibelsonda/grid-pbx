<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;
use stdClass;

final readonly class ManagedExtensionCallflowWriteData
{
    /** @param array<string, mixed> $current */
    public function __construct(
        private array $current,
        public string $userResourceId,
        public string $previousExtension,
        public string $extension,
        public string $name,
        public ?string $voicemailBoxResourceId,
    ) {
        if (trim($this->userResourceId) === '') {
            throw new InvalidArgumentException('Managed extension callflow user identifier is required.');
        }

        if (trim($this->previousExtension) === '' || trim($this->extension) === '') {
            throw new InvalidArgumentException('Managed extension callflow numbers are required.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Managed extension callflow name is required.');
        }

        $flow = $this->current['flow'] ?? null;

        if (! is_array($flow) || ($flow['module'] ?? null) !== 'user') {
            throw new InvalidArgumentException('Managed extension callflow must have a user root node.');
        }

        $currentUserId = is_array($flow['data'] ?? null) ? ($flow['data']['id'] ?? null) : null;

        if ($currentUserId !== $this->userResourceId) {
            throw new InvalidArgumentException('Managed extension callflow user target has diverged.');
        }

        if (! $this->containsPreviousExtension()) {
            throw new InvalidArgumentException('Managed extension callflow no longer contains its extension number.');
        }

        $fallback = is_array($flow['children'] ?? null) ? ($flow['children']['_'] ?? null) : null;

        if ($this->voicemailBoxResourceId !== null
            && is_array($fallback)
            && ($fallback['module'] ?? null) !== 'voicemail') {
            throw new InvalidArgumentException('Managed extension callflow wildcard branch is independently configured.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateFields($this->current);
        $data['name'] = trim($this->name);
        $data['numbers'] = array_values(array_unique(array_map(
            fn (string $number): string => $this->sameExtensionNumber($number)
                ? $this->extension
                : $number,
            $this->stringNumbers(),
        )));

        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];
        $flowData = is_array($flow['data'] ?? null) ? $flow['data'] : [];
        $flowData['id'] = $this->userResourceId;
        $flow['data'] = $flowData;
        $children = is_array($flow['children'] ?? null) ? $flow['children'] : [];

        if ($this->voicemailBoxResourceId === null) {
            if (is_array($children['_'] ?? null) && ($children['_']['module'] ?? null) === 'voicemail') {
                unset($children['_']);
            }
        } else {
            $children['_'] = [
                'module' => 'voicemail',
                'data' => ['id' => $this->voicemailBoxResourceId],
                'children' => new stdClass,
            ];
        }

        $flow['children'] = $children;
        $data['flow'] = $this->normalizeChildrenMaps($flow);

        return $data;
    }

    /** @return list<string> */
    private function stringNumbers(): array
    {
        return array_values(array_filter(
            is_array($this->current['numbers'] ?? null) ? $this->current['numbers'] : [],
            static fn (mixed $number): bool => is_string($number) && $number !== '',
        ));
    }

    private function containsPreviousExtension(): bool
    {
        foreach ($this->stringNumbers() as $number) {
            if ($this->sameExtensionNumber($number)) {
                return true;
            }
        }

        return false;
    }

    private function sameExtensionNumber(string $number): bool
    {
        return $number === $this->previousExtension
            || $number === "+{$this->previousExtension}";
    }

    /** @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function normalizeChildrenMaps(array $node): array
    {
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        foreach ($children as $key => $child) {
            if (is_array($child)) {
                $children[$key] = $this->normalizeChildrenMaps($child);
            }
        }

        $node['children'] = $children === [] ? new stdClass : $children;

        return $node;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withoutPrivateFields(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (
                $key === 'id'
                || $key === '_id'
                || $key === '_rev'
                || $key === 'account_id'
                || $key === 'created'
                || $key === 'modified'
                || str_starts_with($key, 'pvt_')
            ) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
