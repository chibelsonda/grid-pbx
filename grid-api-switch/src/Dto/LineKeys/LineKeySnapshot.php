<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\LineKeys;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class LineKeySnapshot
{
    private const TYPES = ['line', 'presence', 'personal_parking', 'speed_dial', 'parking'];

    public string $type;

    public ?string $label;

    public string|int|null $value;

    /** @param array<string, mixed>|null $data */
    public function __construct(
        public string $category,
        public int $position,
        public ?array $data,
    ) {
        if (! in_array($category, ['combo', 'feature'], true)) {
            throw new InvalidSwitchPayloadException('Switch line-key category must be combo or feature.');
        }

        if ($position < 0) {
            throw new InvalidSwitchPayloadException('Switch line-key position must be zero or greater.');
        }

        $type = $data['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            throw new InvalidSwitchPayloadException('Switch line-key type is invalid.');
        }

        $this->type = $type;
        $rawValue = $data['value'] ?? null;

        if (is_array($rawValue)) {
            $label = $rawValue['label'] ?? null;
            $value = $rawValue['value'] ?? null;
            $this->label = is_string($label) && $label !== '' ? $label : null;
            $this->value = is_string($value) || is_int($value) ? $value : null;
        } else {
            $this->label = null;
            $this->value = is_string($rawValue) || is_int($rawValue) ? $rawValue : null;
        }

        if ($this->type !== 'parking' && is_int($this->value)) {
            throw new InvalidSwitchPayloadException('Non-parking Switch line-key values must be strings.');
        }

        if ($this->type === 'parking' && $this->value !== null) {
            $parkingPosition = filter_var($this->value, FILTER_VALIDATE_INT);

            if ($parkingPosition === false || $parkingPosition < 1 || $parkingPosition > 10) {
                throw new InvalidSwitchPayloadException('Switch parking line-key value must be between 1 and 10.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'position' => $this->position,
            'type' => $this->type,
            'label' => $this->label,
            'value' => $this->value,
            'data' => $this->data,
        ];
    }
}
