<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

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

        if (! in_array($this->previousExtension, $this->stringNumbers(), true)) {
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
            fn (string $number): string => $number === $this->previousExtension
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
                'children' => [],
            ];
        }

        $flow['children'] = $children;
        $data['flow'] = $flow;

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
