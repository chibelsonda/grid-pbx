<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\Media\MediaSnapshot;
use GridPbx\Switch\Dto\Media\MediaWriteData;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

final readonly class MediaResourceClient
{
    public function __construct(private SwitchClient $client) {}

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
