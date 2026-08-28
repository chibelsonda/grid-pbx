<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\Voicemail\VoicemailMessageBulkResult;
use GridPbx\Switch\Dto\Voicemail\VoicemailMessageFolder;
use GridPbx\Switch\Dto\Voicemail\VoicemailMessageSnapshot;
use GridPbx\Switch\Dto\Voicemail\VoicemailBoxSnapshot;
use GridPbx\Switch\Dto\Voicemail\VoicemailBoxWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class VoicemailBoxResourceClient
{
    public function __construct(private SwitchClient $client, private int $messagePageSize = 200)
    {
        if ($this->messagePageSize < 1) {
            throw new InvalidArgumentException('Switch voicemail message page size must be greater than zero.');
        }
    }

    public function create(string $accountId, VoicemailBoxWriteData $voicemailBox): VoicemailBoxSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'PUT',
            sprintf('accounts/%s/vmboxes', rawurlencode($accountId)),
            ['json' => ['data' => $voicemailBox->toSwitchData()]],
        );

        return $this->snapshot($payload);
    }

    public function update(
        string $accountId,
        string $voicemailBoxId,
        VoicemailBoxWriteData $voicemailBox,
    ): VoicemailBoxSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/vmboxes/%s',
                rawurlencode($accountId),
                rawurlencode($voicemailBoxId),
            ),
            ['json' => ['data' => $voicemailBox->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $voicemailBoxId) {
            throw new InvalidSwitchPayloadException('Switch voicemail box response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $voicemailBoxId): void
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $this->client->request(
            'DELETE',
            sprintf(
                'accounts/%s/vmboxes/%s',
                rawurlencode($accountId),
                rawurlencode($voicemailBoxId),
            ),
        );
    }

    public function setUnavailableGreeting(
        string $accountId,
        string $voicemailBoxId,
        ?string $mediaId,
    ): VoicemailBoxSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $payload = $this->client->request(
            'PATCH',
            sprintf(
                'accounts/%s/vmboxes/%s',
                rawurlencode($accountId),
                rawurlencode($voicemailBoxId),
            ),
            ['json' => ['data' => ['media' => ['unavailable' => $mediaId]]]],
        );

        return $this->snapshot($payload);
    }

    /** @return Generator<int, VoicemailMessageSnapshot> */
    public function allMessages(string $accountId, string $voicemailBoxId): Generator
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $cursor = null;
        $seenCursors = [];

        do {
            $query = ['paginate' => 'true', 'page_size' => $this->messagePageSize];

            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request(
                'GET',
                sprintf(
                    'accounts/%s/vmboxes/%s/messages',
                    rawurlencode($accountId),
                    rawurlencode($voicemailBoxId),
                ),
                ['query' => $query],
            );
            $messages = $payload['data'] ?? null;

            if (! is_array($messages)) {
                throw new InvalidSwitchPayloadException('Switch voicemail message response data must be an array.');
            }

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    throw new InvalidSwitchPayloadException('Switch voicemail message entries must be objects.');
                }

                yield new VoicemailMessageSnapshot($message);
            }

            $nextCursor = $payload['next_start_key'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch voicemail message pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function messageAudio(
        string $accountId,
        string $voicemailBoxId,
        string $messageId,
        ?string $range = null,
    ): BinaryResponse {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $messageId = $this->requiredIdentifier($messageId, 'voicemail message');
        $headers = [];

        if ($range !== null) {
            $headers['Range'] = $range;
        }

        return $this->client->binary(
            'GET',
            sprintf(
                'accounts/%s/vmboxes/%s/messages/%s/raw',
                rawurlencode($accountId),
                rawurlencode($voicemailBoxId),
                rawurlencode($messageId),
            ),
            ['headers' => $headers],
        );
    }

    public function changeMessageFolder(
        string $accountId,
        string $voicemailBoxId,
        string $messageId,
        VoicemailMessageFolder $folder,
    ): VoicemailMessageSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $messageId = $this->requiredIdentifier($messageId, 'voicemail message');
        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/vmboxes/%s/messages/%s',
                rawurlencode($accountId),
                rawurlencode($voicemailBoxId),
                rawurlencode($messageId),
            ),
            ['json' => ['data' => ['folder' => $folder->value]]],
        );
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch voicemail message response data must be an object.');
        }

        return new VoicemailMessageSnapshot($data);
    }

    /** @param list<string> $messageIds */
    public function changeMessagesFolder(
        string $accountId,
        string $voicemailBoxId,
        array $messageIds,
        VoicemailMessageFolder $folder,
    ): VoicemailMessageBulkResult {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $voicemailBoxId = $this->requiredIdentifier($voicemailBoxId, 'voicemail box');
        $messageIds = $this->requiredIdentifiers($messageIds, 'voicemail message');
        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/vmboxes/%s/messages',
                rawurlencode($accountId),
                rawurlencode($voicemailBoxId),
            ),
            ['json' => ['data' => ['folder' => $folder->value, 'messages' => $messageIds]]],
        );
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch voicemail bulk response data must be an object.');
        }

        return new VoicemailMessageBulkResult($data);
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): VoicemailBoxSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch voicemail box response data must be an object.');
        }

        return new VoicemailBoxSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }

    /**
     * @param list<string> $identifiers
     * @return list<string>
     */
    private function requiredIdentifiers(array $identifiers, string $name): array
    {
        if ($identifiers === []) {
            throw new InvalidArgumentException(sprintf('At least one Switch %s identifier is required.', $name));
        }

        $validated = [];

        foreach ($identifiers as $identifier) {
            $validated[] = $this->requiredIdentifier($identifier, $name);
        }

        return array_values(array_unique($validated));
    }
}
