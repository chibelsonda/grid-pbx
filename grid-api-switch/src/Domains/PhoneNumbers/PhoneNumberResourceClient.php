<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\PhoneNumbers;

use Generator;
use GridPbx\Switch\Domains\PhoneNumbers\Dto\NumberClassifierSnapshot;
use GridPbx\Switch\Domains\PhoneNumbers\Dto\PhoneNumberSnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class PhoneNumberResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch phone number page size must be greater than zero.');
        }
    }

    /** @return Generator<int, PhoneNumberSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $cursor = null;
        $seenCursors = [];

        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize];

            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request(
                'GET',
                sprintf('accounts/%s/phone_numbers', rawurlencode($accountId)),
                ['query' => $query],
            );
            $data = $payload['data'] ?? null;
            $numbers = is_array($data) ? ($data['numbers'] ?? null) : null;

            if (! is_array($numbers)) {
                throw new InvalidSwitchPayloadException('Switch phone number collection data.numbers must be an object or array.');
            }

            foreach ($numbers as $key => $summary) {
                if (! is_array($summary)) {
                    throw new InvalidSwitchPayloadException('Switch phone number collection entries must be objects.');
                }

                $number = is_string($key) && $key !== ''
                    ? $key
                    : ($summary['number'] ?? ($summary['id'] ?? null));

                if (! is_string($number) || $number === '') {
                    throw new InvalidSwitchPayloadException('Switch phone number collection entry must identify its number.');
                }

                yield $this->find($accountId, $number);
            }

            $nextCursor = $payload['next_start_key'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch phone number pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function find(string $accountId, string $number): PhoneNumberSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $number = $this->requiredIdentifier($number, 'phone number');
        $payload = $this->client->request(
            'GET',
            sprintf(
                'accounts/%s/phone_numbers/%s',
                rawurlencode($accountId),
                rawurlencode($number),
            ),
        );
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch phone number detail data must be an object.');
        }

        $snapshot = new PhoneNumberSnapshot($data);

        if ($snapshot->number !== $number) {
            throw new InvalidSwitchPayloadException('Switch phone number response id does not match the requested number.');
        }

        return $snapshot;
    }

    /** @return list<NumberClassifierSnapshot> */
    public function classifiers(string $accountId): array
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'GET',
            sprintf('accounts/%s/phone_numbers/classifiers', rawurlencode($accountId)),
        );
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch number classifier response data must be an object.');
        }

        $classifiers = [];

        foreach ($data as $key => $classifier) {
            if (! is_string($key) || ! is_array($classifier)) {
                throw new InvalidSwitchPayloadException('Switch number classifier entries must be keyed objects.');
            }

            $classifiers[] = new NumberClassifierSnapshot($key, $classifier);
        }

        return $classifiers;
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
