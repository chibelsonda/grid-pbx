<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use Generator;
use GridPbx\Switch\Dto\Recordings\RecordingSnapshot;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class RecordingResourceClient
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) throw new InvalidArgumentException('Switch recording page size must be greater than zero.');
    }

    /** @return Generator<int, RecordingSnapshot> */
    public function all(string $accountId, int $createdFromUnix, int $createdToUnix): Generator
    {
        $accountId = $this->required($accountId, 'account');
        if ($createdFromUnix < 0 || $createdToUnix < $createdFromUnix) throw new InvalidArgumentException('Switch recording time range is invalid.');
        $cursor = null; $seen = [];
        do {
            $query = ['created_from' => $createdFromUnix + self::GREGORIAN_UNIX_OFFSET, 'created_to' => $createdToUnix + self::GREGORIAN_UNIX_OFFSET, 'paginate' => 'true', 'page_size' => $this->pageSize];
            if ($cursor !== null) $query['start_key'] = $cursor;
            $payload = $this->client->request('GET', sprintf('accounts/%s/recordings', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;
            if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch recording collection response data must be an array.');
            foreach ($data as $recording) {
                if (! is_array($recording)) throw new InvalidSwitchPayloadException('Switch recording collection entries must be objects.');
                yield new RecordingSnapshot($recording);
            }
            $next = $payload['next_start_key'] ?? null; $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor !== null && isset($seen[$cursor])) throw new InvalidSwitchPayloadException('Switch recording pagination returned a repeated cursor.');
            if ($cursor !== null) $seen[$cursor] = true;
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $recordingId): RecordingSnapshot
    {
        $payload = $this->client->request('GET', $this->path($accountId, $recordingId));
        $data = $payload['data'] ?? null;
        if (! is_array($data)) throw new InvalidSwitchPayloadException('Switch recording response data must be an object.');
        $snapshot = new RecordingSnapshot($data);
        if ($snapshot->id !== $recordingId) throw new InvalidSwitchPayloadException('Switch recording response id does not match the requested resource.');
        return $snapshot;
    }

    public function audio(string $accountId, string $recordingId, ?string $range = null): BinaryResponse
    {
        $headers = ['Accept' => 'audio/mpeg'];
        if ($range !== null) $headers['Range'] = $range;
        return $this->client->binary('GET', $this->path($accountId, $recordingId), ['query' => ['inline' => 'true'], 'headers' => $headers]);
    }

    public function delete(string $accountId, string $recordingId): void { $this->client->request('DELETE', $this->path($accountId, $recordingId)); }
    private function path(string $accountId, string $recordingId): string { return sprintf('accounts/%s/recordings/%s', rawurlencode($this->required($accountId, 'account')), rawurlencode($this->required($recordingId, 'recording'))); }
    private function required(string $id, string $name): string { if ($id === '') throw new InvalidArgumentException("Switch {$name} identifier is required."); return $id; }
}
