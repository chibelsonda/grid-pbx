<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Groups\Dto;

use InvalidArgumentException;

final readonly class GroupWriteData
{
    /**
     * @param  list<GroupEndpointWriteData>  $endpoints
     * @param  list<string>  $flags
     * @param  array<string, mixed>  $preservedOptions
     */
    public function __construct(
        public string $name,
        public array $endpoints,
        public ?string $musicOnHoldMediaId = null,
        public array $flags = [],
        public array $preservedOptions = [],
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 128) {
            throw new InvalidArgumentException('Switch group name must contain between 1 and 128 characters.');
        }

        if ($this->musicOnHoldMediaId !== null
            && ($this->musicOnHoldMediaId === '' || mb_strlen($this->musicOnHoldMediaId) > 128)) {
            throw new InvalidArgumentException('Switch group music-on-hold media identifier is invalid.');
        }

        foreach ($this->endpoints as $endpoint) {
            if (! $endpoint instanceof GroupEndpointWriteData) {
                throw new InvalidArgumentException('Switch group endpoints must be typed endpoint data.');
            }
        }

        foreach ($this->flags as $flag) {
            if (! is_string($flag) || $flag === '') {
                throw new InvalidArgumentException('Switch group flags must contain non-empty strings.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $endpoints = [];
        $preservedEndpoints = is_array($this->preservedOptions['endpoints'] ?? null)
            ? $this->preservedOptions['endpoints']
            : [];

        foreach ($this->endpoints as $endpoint) {
            $preserved = is_array($preservedEndpoints[$endpoint->resourceId] ?? null)
                ? array_diff_key($preservedEndpoints[$endpoint->resourceId], array_flip(['type', 'weight']))
                : [];
            $endpoints[$endpoint->resourceId] = array_merge($preserved, [
                'type' => $endpoint->type,
                'weight' => $endpoint->weight,
            ]);
        }

        $musicOnHold = is_array($this->preservedOptions['music_on_hold'] ?? null)
            ? array_diff_key($this->preservedOptions['music_on_hold'], ['media_id' => true])
            : [];

        if ($this->musicOnHoldMediaId !== null) {
            $musicOnHold['media_id'] = $this->musicOnHoldMediaId;
        }

        $preserved = array_diff_key(
            $this->preservedOptions,
            array_flip(['name', 'endpoints', 'music_on_hold', 'flags']),
        );

        return array_merge($preserved, [
            'name' => $this->name,
            'endpoints' => $endpoints,
            'music_on_hold' => $musicOnHold === [] ? (object) [] : $musicOnHold,
            'flags' => array_values($this->flags),
        ]);
    }
}
