<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Menus;

use Generator;
use GridPbx\Switch\Domains\Menus\Dto\MenuSnapshot;
use GridPbx\Switch\Domains\Menus\Dto\MenuWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class MenuResourceClient
{
    public function __construct(private SwitchClient $client, private int $pageSize = 200)
    {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Switch page size must be greater than zero.');
        }
    }

    /** @return Generator<int, MenuSnapshot> */
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

            $payload = $this->client->request('GET', sprintf('accounts/%s/menus', rawurlencode($accountId)), ['query' => $query]);
            $data = $payload['data'] ?? null;

            if (! is_array($data)) {
                throw new InvalidSwitchPayloadException('Switch menu collection response data must be an array.');
            }

            foreach ($data as $summary) {
                $id = is_array($summary) ? ($summary['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    throw new InvalidSwitchPayloadException('Switch menu collection entry must contain an id.');
                }

                yield $this->get($accountId, $id);
            }

            $next = $payload['next_start_key'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;

            if ($cursor !== null && isset($seen[$cursor])) {
                throw new InvalidSwitchPayloadException('Switch menu pagination returned a repeated cursor.');
            }

            if ($cursor !== null) {
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    public function get(string $accountId, string $menuId): MenuSnapshot
    {
        return $this->snapshot($this->client->request('GET', $this->path($accountId, $menuId)));
    }

    public function create(string $accountId, MenuWriteData $menu): MenuSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');

        return $this->snapshot($this->client->request('PUT', sprintf('accounts/%s/menus', rawurlencode($accountId)), [
            'json' => ['data' => $menu->toSwitchData()],
        ]));
    }

    public function update(string $accountId, string $menuId, MenuWriteData $menu): MenuSnapshot
    {
        $current = $this->get($accountId, $menuId);
        $data = $menu->toSwitchData($current->data);

        if ($menu->recordPin === null && ! $menu->clearRecordPin) {
            if ($current->recordPin !== null) {
                $data['record_pin'] = $current->recordPin;
            }
        }

        $snapshot = $this->snapshot($this->client->request('POST', $this->path($accountId, $menuId), [
            'json' => ['data' => $data],
        ]));

        if ($snapshot->id !== $menuId) {
            throw new InvalidSwitchPayloadException('Switch menu response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $menuId): void
    {
        $this->client->request('DELETE', $this->path($accountId, $menuId));
    }

    private function path(string $accountId, string $menuId): string
    {
        return sprintf('accounts/%s/menus/%s', rawurlencode($this->requiredIdentifier($accountId, 'account')), rawurlencode($this->requiredIdentifier($menuId, 'menu')));
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): MenuSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch menu response data must be an object.');
        }

        return new MenuSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
