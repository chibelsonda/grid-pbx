<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

final readonly class CallflowEntryPointsWriteData
{
    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $assignedEntryNumbers
     * @param  list<string>  $knownEntryNumbers
     */
    public function __construct(
        private array $current,
        private array $assignedEntryNumbers,
        private array $knownEntryNumbers,
    ) {
        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its entry points can be edited.');
        }

        foreach ([...$this->assignedEntryNumbers, ...$this->knownEntryNumbers] as $number) {
            if (! is_string($number) || trim($number) === '') {
                throw new InvalidArgumentException('Switch callflow entry numbers must be non-empty strings.');
            }
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

        $currentNumbers = is_array($data['numbers'] ?? null) ? $data['numbers'] : [];
        $preservedNumbers = array_values(array_filter(
            $currentNumbers,
            fn (mixed $number): bool => is_string($number)
                && $number !== ''
                && ! in_array($number, $this->knownEntryNumbers, true),
        ));
        $data['numbers'] = array_values(array_unique([
            ...$preservedNumbers,
            ...$this->assignedEntryNumbers,
        ]));

        return $data;
    }
}
