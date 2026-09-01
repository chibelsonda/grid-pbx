<?php

namespace App\Domains\Queues\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Exceptions\QueueStatisticsUnavailableException;
use App\Domains\Queues\Models\SwitchQueue;
use Illuminate\Support\Collection;

class QueueStatisticsService
{
    public function __construct(
        private readonly SwitchQueueGateway $gateway,
        private readonly QueueCapabilityService $capabilities,
    ) {}

    /**
     * Aggregate the private Switch feed before it crosses the API boundary.
     *
     * @return array{
     *   observed_at: string,
     *   totals: array{waiting: int, handled: int, abandoned: int, processed: int, average_wait_seconds: int|null, average_talk_seconds: int|null, longest_current_wait_seconds: int},
     *   queues: list<array{id: string, name: string, waiting: int, handled: int, abandoned: int, processed: int, average_wait_seconds: int|null, average_talk_seconds: int|null, longest_current_wait_seconds: int}>,
     *   unresolved_records: int
     * }
     */
    public function get(SwitchAccount $account): array
    {
        if (! $this->capabilities->get($account)['statistics_available']) {
            throw new QueueStatisticsUnavailableException;
        }

        $snapshot = $this->gateway->statistics($account);
        $queues = $account->queues()
            ->orderBy('name')
            ->get(['queue_id', 'id', 'name', 'switch_resource_id'])
            ->keyBy('switch_resource_id');
        $statistics = $snapshot['statistics'];
        $grouped = collect($statistics)->groupBy('queue_id');
        $unresolved = collect($statistics)->filter(
            static fn (array $statistic): bool => ! $queues->has($statistic['queue_id']),
        )->count();
        $queueMetrics = $queues->map(
            fn (SwitchQueue $queue): array => [
                'id' => $queue->id,
                'name' => $queue->name,
                ...$this->metrics($grouped->get($queue->switch_resource_id, collect()), $snapshot['current_timestamp']),
            ],
        )->values()->all();

        return [
            'observed_at' => now()->toIso8601String(),
            'totals' => $this->metrics(collect($statistics), $snapshot['current_timestamp']),
            'queues' => $queueMetrics,
            'unresolved_records' => $unresolved,
        ];
    }

    /**
     * @param  Collection<int, array{queue_id: string, status: string, entered_timestamp: int|null, wait_time: int|null, talk_time: int|null}>  $statistics
     * @return array{waiting: int, handled: int, abandoned: int, processed: int, average_wait_seconds: int|null, average_talk_seconds: int|null, longest_current_wait_seconds: int}
     */
    private function metrics(Collection $statistics, int $currentTimestamp): array
    {
        $waitTimes = $statistics->pluck('wait_time')->filter(static fn (mixed $value): bool => is_int($value));
        $talkTimes = $statistics->pluck('talk_time')->filter(static fn (mixed $value): bool => is_int($value));
        $waitingTimes = $statistics
            ->where('status', 'waiting')
            ->pluck('entered_timestamp')
            ->filter(static fn (mixed $value): bool => is_int($value) && $value <= $currentTimestamp)
            ->map(static fn (int $value): int => $currentTimestamp - $value);

        return [
            'waiting' => $statistics->where('status', 'waiting')->count(),
            'handled' => $statistics->where('status', 'handled')->count(),
            'abandoned' => $statistics->where('status', 'abandoned')->count(),
            'processed' => $statistics->where('status', 'processed')->count(),
            'average_wait_seconds' => $waitTimes->isEmpty() ? null : (int) round($waitTimes->average()),
            'average_talk_seconds' => $talkTimes->isEmpty() ? null : (int) round($talkTimes->average()),
            'longest_current_wait_seconds' => $waitingTimes->isEmpty() ? 0 : (int) $waitingTimes->max(),
        ];
    }
}
