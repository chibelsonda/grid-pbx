<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Media;

use Generator;
use GridPbx\Switch\Domains\Media\Dto\MediaSnapshot;
use GridPbx\Switch\Domains\Media\Dto\MediaWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

final readonly class MediaResourceClient
{
    public function __construct(
        private SwitchClient $client,
        private int $pageSize = 200,
    ) {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, MediaSnapshot> */
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
                sprintf('accounts/%s/media', rawurlencode($accountId)),
                ['query' => $query],
            );
            $summaries = $payload['data'] ?? null;

            if (! is_array($summaries)) {
                throw new InvalidSwitchPayloadException('Switch media collection response data must be an array.');
            }

            foreach ($summaries as $summary) {
                if (! is_array($summary)) {
                    throw new InvalidSwitchPayloadException('Switch media collection entries must be objects.');
                }

                $mediaId = $summary['id'] ?? null;

                if (! is_string($mediaId) || $mediaId === '') {
                    throw new InvalidSwitchPayloadException('Switch media collection entry must contain a non-empty string id.');
                }

                yield $this->get($accountId, $mediaId);
            }

            $nextCursor = $payload['next_start_key'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch media pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $mediaId): MediaSnapshot
    {
        $payload = $this->client->request('GET', $this->mediaPath($accountId, $mediaId));

        return $this->snapshot($payload);
    }

    public function create(string $accountId, MediaWriteData $media): MediaSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'PUT',
            sprintf('accounts/%s/media', rawurlencode($accountId)),
            ['json' => ['data' => $media->toSwitchData()]],
        );

        return $this->snapshot($payload);
    }

    public function update(string $accountId, string $mediaId, MediaWriteData $media): MediaSnapshot
    {
        $payload = $this->client->request(
            'POST',
            $this->mediaPath($accountId, $mediaId),
            ['json' => ['data' => $media->toSwitchData()]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $mediaId) {
            throw new InvalidSwitchPayloadException('Switch media response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function upload(
        string $accountId,
        string $mediaId,
        StreamInterface $stream,
        string $contentType,
        ?int $contentLength = null,
    ): MediaSnapshot {
        if (! str_starts_with($contentType, 'audio/')) {
            throw new InvalidArgumentException('Switch media upload content type must be audio.');
        }

        $headers = ['Content-Type' => $contentType];

        if ($contentLength !== null) {
            $headers['Content-Length'] = (string) $contentLength;
        }

        $this->client->request(
            'POST',
            $this->mediaPath($accountId, $mediaId).'/raw',
            ['headers' => $headers, 'body' => $stream],
        );

        return $this->get($accountId, $mediaId);
    }

    public function raw(string $accountId, string $mediaId, ?string $range = null): BinaryResponse
    {
        $headers = [];

        if ($range !== null) {
            $headers['Range'] = $range;
        }

        return $this->client->binary(
            'GET',
            $this->mediaPath($accountId, $mediaId).'/raw',
            ['headers' => $headers],
        );
    }

    public function delete(string $accountId, string $mediaId): void
    {
        $this->client->request('DELETE', $this->mediaPath($accountId, $mediaId));
    }

    private function mediaPath(string $accountId, string $mediaId): string
    {
        return sprintf(
            'accounts/%s/media/%s',
            rawurlencode($this->requiredIdentifier($accountId, 'account')),
            rawurlencode($this->requiredIdentifier($mediaId, 'media')),
        );
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): MediaSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch media response data must be an object.');
        }

        return new MediaSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
