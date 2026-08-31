<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Menus\Dto;

use InvalidArgumentException;

final readonly class MenuWriteData
{
    /** @param list<string> $flags */
    public function __construct(
        public string $name,
        public int $timeout = 10000,
        public int $interdigitTimeout = 2000,
        public int $maxExtensionLength = 4,
        public int $retries = 3,
        public bool $hunt = true,
        public bool $allowRecordFromOffnet = false,
        public bool $suppressMedia = false,
        public ?string $recordPin = null,
        public bool $clearRecordPin = false,
        public ?string $huntAllow = null,
        public ?string $huntDeny = null,
        public ?string $greetingMediaId = null,
        public string|bool|null $invalidMedia = true,
        public string|bool|null $transferMedia = true,
        public string|bool|null $exitMedia = true,
        public array $flags = [],
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 128) {
            throw new InvalidArgumentException('Switch menu name must contain between 1 and 128 characters.');
        }

        if ($this->timeout < 1 || $this->timeout > 60000 || $this->interdigitTimeout < 1 || $this->interdigitTimeout > 10000) {
            throw new InvalidArgumentException('Switch menu timeout settings are invalid.');
        }

        if ($this->maxExtensionLength < 1 || $this->maxExtensionLength > 6 || $this->retries < 1 || $this->retries > 10) {
            throw new InvalidArgumentException('Switch menu digit settings are invalid.');
        }

        if ($this->recordPin !== null && (strlen($this->recordPin) < 3 || strlen($this->recordPin) > 6 || ! ctype_digit($this->recordPin))) {
            throw new InvalidArgumentException('Switch menu record PIN must contain 3 to 6 digits.');
        }

        if ($this->recordPin !== null && $this->clearRecordPin) {
            throw new InvalidArgumentException('A Switch menu record PIN cannot be set and cleared in the same request.');
        }

        foreach ([$this->huntAllow, $this->huntDeny] as $pattern) {
            if ($pattern !== null && ($pattern === '' || mb_strlen($pattern) > 256)) {
                throw new InvalidArgumentException('Switch menu hunt patterns must contain between 1 and 256 characters.');
            }
        }

        foreach ([$this->greetingMediaId, $this->invalidMedia, $this->transferMedia, $this->exitMedia] as $media) {
            if (is_string($media) && (mb_strlen($media) < 3 || mb_strlen($media) > 2048)) {
                throw new InvalidArgumentException('Switch menu media references must contain between 3 and 2048 characters.');
            }
        }

        foreach ($this->flags as $flag) {
            if (! is_string($flag) || $flag === '') {
                throw new InvalidArgumentException('Switch menu flags must contain non-empty strings.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $preservedOptions
     * @return array<string, mixed>
     */
    public function toSwitchData(array $preservedOptions = []): array
    {
        $preserved = $this->withoutPrivateOrRedactedValues(array_diff_key(
            $preservedOptions,
            array_flip([
                'id', 'name', 'timeout', 'interdigit_timeout', 'max_extension_length',
                'retries', 'hunt', 'allow_record_from_offnet', 'suppress_media',
                'record_pin', 'hunt_allow', 'hunt_deny', 'media', 'flags',
            ]),
        ));
        $preservedMedia = is_array($preservedOptions['media'] ?? null)
            ? $this->withoutPrivateOrRedactedValues(array_diff_key(
                $preservedOptions['media'],
                array_flip(['greeting', 'invalid_media', 'transfer_media', 'exit_media']),
            ))
            : [];
        $media = array_merge($preservedMedia, array_filter([
            'greeting' => $this->greetingMediaId,
            'invalid_media' => $this->invalidMedia,
            'transfer_media' => $this->transferMedia,
            'exit_media' => $this->exitMedia,
        ], static fn (mixed $value): bool => $value !== null));

        $data = array_merge($preserved, [
            'name' => $this->name,
            'timeout' => $this->timeout,
            'interdigit_timeout' => $this->interdigitTimeout,
            'max_extension_length' => $this->maxExtensionLength,
            'retries' => $this->retries,
            'hunt' => $this->hunt,
            'allow_record_from_offnet' => $this->allowRecordFromOffnet,
            'suppress_media' => $this->suppressMedia,
            'flags' => array_values($this->flags),
            'media' => $media === [] ? (object) [] : $media,
        ]);

        foreach (['record_pin' => $this->recordPin, 'hunt_allow' => $this->huntAllow, 'hunt_deny' => $this->huntDeny] as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function withoutPrivateOrRedactedValues(array $values): array
    {
        $safe = [];
        $isList = array_is_list($values);

        foreach ($values as $key => $value) {
            if ((is_string($key)
                    && (str_starts_with($key, '_') || str_starts_with($key, 'pvt_')))
                || $value === '[REDACTED]') {
                continue;
            }

            $safe[$key] = is_array($value)
                ? $this->withoutPrivateOrRedactedValues($value)
                : $value;
        }

        return $isList ? array_values($safe) : $safe;
    }
}
