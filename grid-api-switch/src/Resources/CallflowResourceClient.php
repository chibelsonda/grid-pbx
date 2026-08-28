<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\Callflows\CallflowCreateData;
use GridPbx\Switch\Dto\Callflows\CallflowSnapshot;
use GridPbx\Switch\Dto\Callflows\CallflowWriteData;
use GridPbx\Switch\Dto\Callflows\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class CallflowResourceClient
{
    public function __construct(private SwitchClient $client)
    {
    }

    public function create(string $accountId, CallflowCreateData $callflow): CallflowSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'PUT',
            sprintf('accounts/%s/callflows', rawurlencode($accountId)),
            ['json' => ['data' => $callflow->toSwitchData()]],
        );

        return $this->snapshot($payload);
    }

    public function update(
        string $accountId,
        string $callflowId,
        CallflowWriteData $callflow,
    ): CallflowSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/callflows/%s',
                rawurlencode($accountId),
                rawurlencode($callflowId),
            ),
            ['json' => ['data' => $callflow->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $callflowId): void
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $this->client->request(
            'DELETE',
            sprintf(
                'accounts/%s/callflows/%s',
                rawurlencode($accountId),
                rawurlencode($callflowId),
            ),
        );
    }

    public function updateManagedExtension(
        string $accountId,
        string $callflowId,
        ManagedExtensionCallflowWriteData $callflow,
    ): CallflowSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/callflows/%s',
                rawurlencode($accountId),
                rawurlencode($callflowId),
            ),
            ['json' => ['data' => $callflow->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): CallflowSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch callflow response data must be an object.');
        }

        return new CallflowSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
