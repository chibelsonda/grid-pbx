<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows;

use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowInlineNodeWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeMoveData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeNodeDeleteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeNodeWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeReorderData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class CallflowResourceClient
{
    public function __construct(private SwitchClient $client) {}

    public function create(string $accountId, CallflowCreateData $callflow): CallflowSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'PUT',
            sprintf('accounts/%s/callflows', rawurlencode($accountId)),
            ['json' => ['data' => $callflow->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        return $this->find($accountId, $snapshot->id);
    }

    public function find(string $accountId, string $callflowId): CallflowSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $payload = $this->client->request(
            'GET',
            sprintf(
                'accounts/%s/callflows/%s',
                rawurlencode($accountId),
                rawurlencode($callflowId),
            ),
            ['query' => ['paginate' => 'false']],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $snapshot;
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

        return $this->find($accountId, $callflowId);
    }

    public function moveTreeNode(
        string $accountId,
        string $callflowId,
        CallflowTreeMoveData $move,
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
            ['json' => ['data' => $move->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $this->find($accountId, $callflowId);
    }

    public function writeTreeNode(
        string $accountId,
        string $callflowId,
        CallflowTreeNodeWriteData $node,
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
            ['json' => ['data' => $node->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $this->find($accountId, $callflowId);
    }

    public function deleteTreeNode(
        string $accountId,
        string $callflowId,
        CallflowTreeNodeDeleteData $node,
    ): CallflowSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $payload = $this->client->request(
            'POST',
            sprintf('accounts/%s/callflows/%s', rawurlencode($accountId), rawurlencode($callflowId)),
            ['json' => ['data' => $node->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $this->find($accountId, $callflowId);
    }

    public function reorderTreeNodes(
        string $accountId,
        string $callflowId,
        CallflowTreeReorderData $reorder,
    ): CallflowSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $payload = $this->client->request(
            'POST',
            sprintf('accounts/%s/callflows/%s', rawurlencode($accountId), rawurlencode($callflowId)),
            ['json' => ['data' => $reorder->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $this->find($accountId, $callflowId);
    }

    public function writeInlineTreeNode(
        string $accountId,
        string $callflowId,
        CallflowInlineNodeWriteData $node,
    ): CallflowSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $callflowId = $this->requiredIdentifier($callflowId, 'callflow');
        $payload = $this->client->request(
            'POST',
            sprintf('accounts/%s/callflows/%s', rawurlencode($accountId), rawurlencode($callflowId)),
            ['json' => ['data' => $node->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $callflowId) {
            throw new InvalidSwitchPayloadException('Switch callflow response id does not match the requested resource.');
        }

        return $this->find($accountId, $callflowId);
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

        return $this->find($accountId, $callflowId);
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
