<?php

namespace App\Domains\KazooSynchronization\Infrastructure\Gateways;

use App\Domains\KazooSynchronization\Application\Contracts\KazooUserGateway;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Generator;
use GridPbx\Kazoo\KazooClient;

class CrossbarKazooUserGateway implements KazooUserGateway
{
    public function __construct(private readonly KazooClient $client) {}

    /** @return Generator<int, array<string, mixed>> */
    public function users(KazooAccount $account): Generator
    {
        $cursor = null;
        $seenCursors = [];

        do {
            $query = ['paginate' => 'true', 'page_size' => 200];

            if (is_string($cursor) && $cursor !== '') {
                $query['start_key'] = $cursor;
            }

            $payload = $this->client->request(
                'GET',
                "accounts/{$account->kazoo_account_id}/users",
                ['query' => $query],
            );

            foreach ($payload['data'] ?? [] as $user) {
                if (is_array($user)) {
                    yield $user;
                }
            }

            $nextCursor = $payload['next_start_key'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor !== null && isset($seenCursors[$cursor])) {
                break;
            }

            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);
    }
}
