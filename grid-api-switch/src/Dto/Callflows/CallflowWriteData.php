<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Callflows;

use InvalidArgumentException;

final readonly class CallflowWriteData
{
    private const SUPPORTED_DESTINATION_MODULES = [
        'user',
        'device',
        'voicemail',
        'callflow',
        'play',
        'directory',
        'group',
        'acdc_member',
        'menu',
        'conference',
        'faxbox',
        'temporal_route',
    ];

    /**
     * @param array<string, mixed> $current
     * @param list<string>|null $assignedPhoneNumbers
     * @param list<string> $knownPhoneNumbers
     */
    public function __construct(
        private array $current,
        public string $destinationModule,
        public string $destinationResourceId,
        public ?string $name = null,
        private ?array $assignedPhoneNumbers = null,
        private array $knownPhoneNumbers = [],
    ) {
        if (! in_array($this->destinationModule, self::SUPPORTED_DESTINATION_MODULES, true)) {
            throw new InvalidArgumentException('Unsupported Switch callflow destination module.');
        }

        if (trim($this->destinationResourceId) === '') {
            throw new InvalidArgumentException('Switch callflow destination identifier is required.');
        }

        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before it can be edited.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->current;

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

        if ($this->name !== null) {
            $data['name'] = trim($this->name);
        }

        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];
        $flow['module'] = $this->destinationModule;
        $flow['data'] = $this->destinationModule === 'temporal_route'
            ? ['rule_set' => $this->destinationResourceId]
            : ['id' => $this->destinationResourceId];
        $flow['children'] = is_array($flow['children'] ?? null) ? $flow['children'] : [];
        $data['flow'] = $flow;

        if ($this->assignedPhoneNumbers !== null) {
            $currentNumbers = is_array($data['numbers'] ?? null) ? $data['numbers'] : [];
            $preservedNumbers = array_values(array_filter(
                $currentNumbers,
                fn (mixed $number): bool => is_string($number)
                    && $number !== ''
                    && ! in_array($number, $this->knownPhoneNumbers, true),
            ));
            $data['numbers'] = array_values(array_unique([
                ...$preservedNumbers,
                ...$this->assignedPhoneNumbers,
            ]));
        }

        return $data;
    }
}
