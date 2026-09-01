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

class ConferenceParticipantService
{
    private const BULK_ACTIONS = ['mute', 'unmute', 'deaf', 'undeaf'];

    public function __construct(
        private readonly SwitchConferenceGateway $gateway,
        private readonly ConferenceParticipantTokenService $tokens,
        private readonly AuditService $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function participants(SwitchAccount $account, SwitchConference $conference): array
    {
        return array_map(
            fn (array $participant): array => [
                ...$participant,
                'id' => $this->tokens->issue($account, $conference, (string) $participant['id']),
            ],
            $this->gateway->participants($account, $conference->switch_resource_id),
        );
    }

    /** @return array{accepted: true, action: string, message: string} */
    public function control(
        SwitchAccount $account,
        SwitchConference $conference,
        User $actor,
        string $participantToken,
        string $action,
        ?string $ipAddress = null,
    ): array {
        return Cache::lock("conference-controls:{$account->getKey()}:{$conference->getKey()}", 15)
            ->block(5, function () use ($account, $conference, $actor, $participantToken, $action, $ipAddress): array {
                try {
                    $participantId = $this->tokens->resolve($account, $conference, $participantToken);
                    $participants = $this->gateway->participants($account, $conference->switch_resource_id);
                    $participant = collect($participants)->first(
                        fn (array $candidate): bool => (string) ($candidate['id'] ?? '') === $participantId,
                    );

                    if ($participant === null) {
                        throw ValidationException::withMessages([
                            'participant_id' => ['The participant is no longer active. Refresh the room and try again.'],
                        ]);
                    }

                    $this->gateway->controlParticipant(
                        $account,
                        $conference->switch_resource_id,
                        $participantId,
                        $action,
                    );
                    $this->audit->record(
                        $actor,
                        $account,
                        "conference.participant.{$action}",
                        'accepted',
                        $conference->switch_resource_id,
                        ['is_moderator' => (bool) ($participant['is_moderator'] ?? false)],
                        $ipAddress,
                        'conference',
                    );

                    return [
                        'accepted' => true,
                        'action' => $action,
                        'message' => "Switch accepted the participant {$action} request.",
                    ];
                } catch (Throwable $exception) {
                    $this->audit->record(
                        $actor,
                        $account,
                        "conference.participant.{$action}",
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

    /** @return array{accepted: true, action: string, targeted_participants: int, skipped_moderators: int, message: string} */
    public function controlAll(
        SwitchAccount $account,
        SwitchConference $conference,
        User $actor,
        string $action,
        int $expectedParticipantCount,
        int $expectedTargetCount,
        ?string $ipAddress = null,
    ): array {
        return Cache::lock("conference-controls:{$account->getKey()}:{$conference->getKey()}", 15)
            ->block(5, function () use (
                $account,
                $conference,
                $actor,
                $action,
                $expectedParticipantCount,
                $expectedTargetCount,
                $ipAddress,
            ): array {
                try {
                    $participants = $this->gateway->participants($account, $conference->switch_resource_id);
                    $participantCount = count($participants);
                    $targetCount = count(array_filter(
                        $participants,
                        fn (array $participant): bool => $this->isBulkTarget($participant, $action),
                    ));

                    if ($participantCount !== $expectedParticipantCount || $targetCount !== $expectedTargetCount) {
                        throw ValidationException::withMessages([
                            'participants' => ['The live room changed after preview. Refresh the room and confirm the command again.'],
                        ]);
                    }

                    if ($targetCount === 0) {
                        throw ValidationException::withMessages([
                            'action' => ['No eligible non-moderator participants currently need this command.'],
                        ]);
                    }

                    $this->gateway->controlParticipants(
                        $account,
                        $conference->switch_resource_id,
                        $action,
                    );
                    $skippedModerators = count(array_filter(
                        $participants,
                        fn (array $participant): bool => (bool) ($participant['is_moderator'] ?? false),
                    ));
                    $this->audit->record(
                        $actor,
                        $account,
                        "conference.participants.{$action}",
                        'accepted',
                        $conference->switch_resource_id,
                        [
                            'observed_participants' => $participantCount,
                            'targeted_participants' => $targetCount,
                            'skipped_moderators' => $skippedModerators,
                        ],
                        $ipAddress,
                        'conference',
                    );

                    return [
                        'accepted' => true,
                        'action' => $action,
                        'targeted_participants' => $targetCount,
                        'skipped_moderators' => $skippedModerators,
                        'message' => "Switch accepted the room-wide {$action} request for {$targetCount} participant(s).",
                    ];
                } catch (Throwable $exception) {
                    $this->audit->record(
                        $actor,
                        $account,
                        "conference.participants.{$action}",
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

    /** @param array<string, mixed> $participant */
    private function isBulkTarget(array $participant, string $action): bool
    {
        if (! in_array($action, self::BULK_ACTIONS, true)
            || (bool) ($participant['is_moderator'] ?? false)) {
            return false;
        }

        return match ($action) {
            'mute' => (bool) ($participant['can_speak'] ?? false),
            'unmute' => ! (bool) ($participant['can_speak'] ?? false),
            'deaf' => (bool) ($participant['can_hear'] ?? false),
            'undeaf' => ! (bool) ($participant['can_hear'] ?? false),
        };
    }
}
