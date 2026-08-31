<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\SystemStatus;

use GridPbx\Switch\Domains\SystemStatus\Dto\OperationalStatus;
use GridPbx\Switch\Shared\Exceptions\SwitchException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class OperationalStatusClient
{
    public function __construct(private SwitchClient $client) {}

    public function inspect(string $accountId): OperationalStatus
    {
        if ($accountId === '') {
            throw new InvalidArgumentException('Switch account identifier is required.');
        }

        $accountPath = sprintf('accounts/%s', rawurlencode($accountId));
        $presenceAvailable = $this->presenceAvailable($accountPath.'/presence');
        [$parkingAvailable, $activeParkedCallCount] = $this->parkingSummary(
            $accountPath.'/parked_calls',
        );
        [$webhookEventCatalogAvailable, $webhookAvailableEventCount] = $this->listSummary(
            'webhooks',
        );
        [
            $webhookConfigurationSummaryAvailable,
            $webhookConfiguredCount,
            $webhookEnabledCount,
        ] = $this->webhookConfigurationSummary($accountPath.'/webhooks');
        $smsInventoryAvailable = $this->collectionAvailable($accountPath.'/sms');
        $mmsInventoryAvailable = $this->collectionAvailable($accountPath.'/mms');
        $portRequestInventoryAvailable = $this->collectionAvailable(
            $accountPath.'/port_requests',
            ['by_number' => 'gridpbx-capability-probe'],
        );
        $numberCarrierConfigurationAvailable = $this->carrierConfigurationAvailable(
            $accountPath.'/phone_numbers/carriers_info',
        );

        return new OperationalStatus(
            presenceSubscriptionDiagnosticsAvailable: $presenceAvailable,
            parkedCallSummaryAvailable: $parkingAvailable,
            activeParkedCallCount: $activeParkedCallCount,
            webhookEventCatalogAvailable: $webhookEventCatalogAvailable,
            webhookAvailableEventCount: $webhookAvailableEventCount,
            webhookConfigurationSummaryAvailable: $webhookConfigurationSummaryAvailable,
            webhookConfiguredCount: $webhookConfiguredCount,
            webhookEnabledCount: $webhookEnabledCount,
            smsInventoryAvailable: $smsInventoryAvailable,
            mmsInventoryAvailable: $mmsInventoryAvailable,
            portRequestInventoryAvailable: $portRequestInventoryAvailable,
            numberCarrierConfigurationAvailable: $numberCarrierConfigurationAvailable,
        );
    }

    private function presenceAvailable(string $path): bool
    {
        try {
            $payload = $this->client->request('GET', $path);

            return is_array($payload['data'] ?? null);
        } catch (SwitchException) {
            return false;
        }
    }

    /** @return array{bool, int|null} */
    private function parkingSummary(string $path): array
    {
        try {
            $payload = $this->client->request('GET', $path);
            $data = $payload['data'] ?? null;
            $slots = is_array($data) ? ($data['slots'] ?? null) : null;

            return is_array($slots) ? [true, count($slots)] : [false, null];
        } catch (SwitchException) {
            return [false, null];
        }
    }

    /** @return array{bool, int|null} */
    private function listSummary(string $path): array
    {
        $items = $this->listItems($path);

        return $items === null ? [false, null] : [true, count($items)];
    }

    /** @return array{bool, int|null, int|null} */
    private function webhookConfigurationSummary(string $path): array
    {
        $hooks = $this->listItems($path);

        if ($hooks === null) {
            return [false, null, null];
        }

        $enabledCount = 0;

        foreach ($hooks as $hook) {
            $enabled = $hook['enabled'] ?? true;

            if (! is_bool($enabled)) {
                return [false, null, null];
            }

            if ($enabled) {
                $enabledCount++;
            }
        }

        return [true, count($hooks), $enabledCount];
    }

    /** @return list<array<string, mixed>>|null */
    private function listItems(string $path): ?array
    {
        try {
            $payload = $this->client->request('GET', $path);
            $data = $payload['data'] ?? null;

            if (! is_array($data) || ! array_is_list($data)) {
                return null;
            }

            foreach ($data as $item) {
                if (! is_array($item)) {
                    return null;
                }
            }

            return $data;
        } catch (SwitchException) {
            return null;
        }
    }

    /** @param array<string, int|string>|null $query */
    private function collectionAvailable(string $path, ?array $query = null): bool
    {
        try {
            $payload = $this->client->request('GET', $path, [
                'query' => $query ?? ['paginate' => 'true', 'page_size' => 1],
            ]);

            return is_array($payload['data'] ?? null) && array_is_list($payload['data']);
        } catch (SwitchException) {
            return false;
        }
    }

    private function carrierConfigurationAvailable(string $path): bool
    {
        try {
            $payload = $this->client->request('GET', $path);
            $data = $payload['data'] ?? null;

            return is_array($data)
                && ! array_is_list($data)
                && is_int($data['maximal_prefix_length'] ?? null)
                && $data['maximal_prefix_length'] > 0
                && $this->isStringList($data['usable_carriers'] ?? null)
                && $this->isStringList($data['usable_creation_states'] ?? null);
        } catch (SwitchException) {
            return false;
        }
    }

    private function isStringList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                return false;
            }
        }

        return true;
    }
}
