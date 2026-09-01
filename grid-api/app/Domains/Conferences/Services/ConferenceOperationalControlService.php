<?php

namespace App\Domains\Conferences\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConferenceOperationalControlService
{
    public function __construct(
        private readonly SwitchConferenceGateway $gateway,
        private readonly ConferenceProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    /** @return array{accepted: true, action: string, message: string} */
    public function control(
        SwitchAccount $account,
        SwitchConference $conference,
        User $actor,
        string $action,
        ?string $ipAddress = null,
    ): array {
        return Cache::lock("conference-controls:{$account->getKey()}:{$conference->getKey()}", 15)
            ->block(5, function () use ($account, $conference, $actor, $action, $ipAddress): array {
                try {
                    $locked = $this->lockedState($action);
                    $snapshot = $this->gateway->get($account, $conference->switch_resource_id);
                    $current = $this->projection->project($account, $snapshot);

                    if ($locked && ($current->active_members + $current->active_moderators) < 1) {
                        throw ValidationException::withMessages([
                            'conference' => ['A conference can only be locked while it has active participants.'],
                        ]);
                    }

                    $this->gateway->setLocked($account, $conference->switch_resource_id, $locked);
                    $this->audit->record(
                        $actor,
                        $account,
                        "conference.{$action}",
                        'accepted',
                        $conference->switch_resource_id,
                        [
                            'observed_participant_count' => $current->active_members + $current->active_moderators,
                            'observed_locked' => $current->is_locked,
                        ],
                        $ipAddress,
                        'conference',
                    );

                    return [
                        'accepted' => true,
                        'action' => $action,
                        'message' => "Switch accepted the conference {$action} request.",
                    ];
                } catch (Throwable $exception) {
                    $this->audit->record(
                        $actor,
                        $account,
                        "conference.{$action}",
                        'failed',
                        $conference->switch_resource_id,
                        ['error_type' => $exception::class],
                        $ipAddress,
                        'conference',
                    );

                    throw $exception;
                }
            });
    }

    private function lockedState(string $action): bool
    {
        return match ($action) {
            'lock' => true,
            'unlock' => false,
            default => throw ValidationException::withMessages([
                'action' => ['Unsupported conference control action.'],
            ]),
        };
    }
}
