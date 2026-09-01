<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues;

use GridPbx\Switch\Domains\Queues\Dto\AcdcCapabilities;
use GridPbx\Switch\Shared\Exceptions\SwitchException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class AcdcCapabilityClient
{
    public function __construct(private SwitchClient $client) {}

    public function discover(string $accountId): AcdcCapabilities
    {
        if ($accountId === '') {
            throw new InvalidArgumentException('Switch account identifier is required.');
        }

        $accountPath = sprintf('accounts/%s', rawurlencode($accountId));

        return new AcdcCapabilities(
            configurationAvailable: $this->respondsSuccessfully(
                $accountPath.'/queues',
                ['query' => ['paginate' => 'true', 'page_size' => 1]],
            ),
            liveAgentControlsAvailable: $this->respondsSuccessfully($accountPath.'/agents/status'),
            agentStatisticsAvailable: $this->respondsSuccessfully($accountPath.'/agents/stats'),
            statisticsAvailable: $this->respondsSuccessfully($accountPath.'/queues/stats'),
        );
    }

    /** @param array<string, mixed> $options */
    private function respondsSuccessfully(string $path, array $options = []): bool
    {
        try {
            $this->client->request('GET', $path, $options);

            return true;
        } catch (SwitchException) {
            return false;
        }
    }
}
