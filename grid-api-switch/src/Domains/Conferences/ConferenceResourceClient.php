<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Conferences;

use Generator;
use GridPbx\Switch\Domains\Conferences\Dto\ConferenceSnapshot;
use GridPbx\Switch\Domains\Conferences\Dto\ConferenceParticipantSnapshot;
use GridPbx\Switch\Domains\Conferences\Dto\ConferenceWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class ConferenceResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, ConferenceSnapshot> */
    public function allDetails(string $accountId): Generator
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $cursor = null;
        $seen = [];

        do {
            $query = ['paginate' => 'true', 'page_size' => $this->pageSize];

            if ($cursor !== null) {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request('GET', sprintf('accounts/%s/conferences', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch conference collection response data must be an array.');
            }

            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch conference collection entry must contain an id.');
                }

                yield $this->get($accountId, $id);
            }

            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;

            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch conference pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $conferenceId): ConferenceSnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $conferenceId)));
    }

    public function create(string $accountId, ConferenceWriteData $conference): ConferenceSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');

        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/conferences', rawurlencode($accountId)), [
            'json' => ['data' => $conference->toSwitchData()],
        ]));
    }

    public function update(string $accountId, string $conferenceId, ConferenceWriteData $conference): ConferenceSnapshot
    {
        $snapshot = $this->snapshot($this->client->request('PATCH', $this->path($accountId, $conferenceId), [
            'json' => ['data' => $conference->toSwitchPatchData()],
        ]));

        if ($snapshot->id !== $conferenceId) {
            throw new InvalidSwitchPayloadException('Switch conference response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $conferenceId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $conferenceId));
    }

    public function setLocked(string $accountId, string $conferenceId, bool $locked): void
    {
        $this->client->request('PUT', $this->path($accountId, $conferenceId), [
            'json' => ['data' => ['action' => $locked ? 'lock' : 'unlock']],
        ]);
    }

    /** @return list<ConferenceParticipantSnapshot> */
    public function participants(string $accountId, string $conferenceId): array
    {
        $payload = $this->client->request('GET', $this->path($accountId, $conferenceId).'/participants');
        $data = $payload['data'] ?? null;

        if (! is_array($data) || ! array_is_list($data)) {
            throw new InvalidSwitchPayloadException('Switch conference participant response data must be an array.');
        }

        return array_map(
            fn (mixed $participant): ConferenceParticipantSnapshot => is_array($participant)
                ? new ConferenceParticipantSnapshot($participant)
                : throw new InvalidSwitchPayloadException('Switch conference participant must be an object.'),
            $data,
        );
    }

    public function controlParticipant(
        string $accountId,
        string $conferenceId,
        string $participantId,
        string $action,
    ): void {
        if (! ctype_digit($participantId) || (int) $participantId < 1) {
            throw new InvalidArgumentException('Switch conference participant identifier must be a positive integer.');
        }

        if (! in_array($action, ['mute', 'unmute', 'deaf', 'undeaf', 'kick'], true)) {
            throw new InvalidArgumentException('Unsupported Switch conference participant action.');
        }

        $this->client->request('PUT', sprintf(
            '%s/participants/%s',
            $this->path($accountId, $conferenceId),
            rawurlencode($participantId),
        ), ['json' => ['data' => ['action' => $action]]]);
    }

    public function controlParticipants(string $accountId, string $conferenceId, string $action): void
    {
        if (! in_array($action, ['mute', 'unmute', 'deaf', 'undeaf'], true)) {
            throw new InvalidArgumentException('Unsupported Switch conference bulk participant action.');
        }

        $this->client->request('PUT', $this->path($accountId, $conferenceId).'/participants', [
            'json' => ['data' => ['action' => $action]],
        ]);
    }

    public function playMedia(
        string $accountId,
        string $conferenceId,
        string $mediaId,
        ?string $participantId = null,
    ): void {
        $mediaId = $this->requiredIdentifier($mediaId, 'media');
        $path = $this->path($accountId, $conferenceId);

        if ($participantId !== null) {
            if (! ctype_digit($participantId) || (int) $participantId < 1) {
                throw new InvalidArgumentException('Switch conference participant identifier must be a positive integer.');
            }

            $path = sprintf('%s/participants/%s', $path, rawurlencode($participantId));
        }

        $this->client->request('PUT', $path, [
            'json' => ['data' => ['action' => 'play', 'data' => ['media_id' => $mediaId]]],
        ]);
    }

    private function path(string $accountId, string $conferenceId): string
    {
        return sprintf(
            'accounts/%s/conferences/%s',
            rawurlencode($this->requiredIdentifier($accountId, 'account')),
            rawurlencode($this->requiredIdentifier($conferenceId, 'conference')),
        );
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): ConferenceSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch conference response data must be an object.');
        }

        return new ConferenceSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
