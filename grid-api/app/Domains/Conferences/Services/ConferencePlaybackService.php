<?php

namespace App\Domains\Conferences\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConferencePlaybackService
{
    public function __construct(
        private readonly SwitchConferenceGateway $gateway,
        private readonly ConferenceParticipantTokenService $tokens,
        private readonly AuditService $audit,
    ) {}

    /** @return array{accepted: true, action: string, target: string, message: string} */
    public function play(
        SwitchAccount $account,
        SwitchConference $conference,
        User $actor,
        string $mediaId,
        ?string $participantToken = null,
        ?string $ipAddress = null,
    ): array {
        return Cache::lock("conference-controls:{$account->getKey()}:{$conference->getKey()}", 15)
            ->block(5, function () use ($account, $conference, $actor, $mediaId, $participantToken, $ipAddress): array {
                $target = $participantToken === null ? 'room' : 'participant';

                try {
                    $media = $this->playableMedia($account, $mediaId);
                    $participants = $this->gateway->participants($account, $conference->switch_resource_id);
                    $participantId = null;
                    $isModerator = null;

                    if ($participants === []) {
                        throw ValidationException::withMessages([
                            'conference' => ['Media can only be played while the conference has active participants.'],
                        ]);
                    }

                    if ($participantToken !== null) {
                        $participantId = $this->tokens->resolve($account, $conference, $participantToken);
                        $participant = collect($participants)->first(
                            fn (array $candidate): bool => (string) ($candidate['id'] ?? '') === $participantId,
                        );

                        if ($participant === null) {
                            throw ValidationException::withMessages([
                                'participant_id' => ['The participant is no longer active. Refresh the room and try again.'],
                            ]);
                        }

                        $isModerator = (bool) ($participant['is_moderator'] ?? false);
                    }

                    $this->gateway->playMedia(
                        $account,
                        $conference->switch_resource_id,
                        $media->switch_resource_id,
                        $participantId,
                    );
                    $this->audit->record(
                        $actor,
                        $account,
                        'conference.media.play',
                        'accepted',
                        $conference->switch_resource_id,
                        array_filter([
                            'media_id' => $media->id,
                            'target' => $target,
                            'is_moderator' => $isModerator,
                        ], fn (mixed $value): bool => $value !== null),
                        $ipAddress,
                        'conference',
                    );

                    return [
                        'accepted' => true,
                        'action' => 'play_media',
                        'target' => $target,
                        'message' => "Switch accepted the media playback request for the {$target}.",
                    ];
                } catch (Throwable $exception) {
                    $this->audit->record(
                        $actor,
                        $account,
                        'conference.media.play',
                        'failed',
                        $conference->switch_resource_id,
                        ['media_id' => $mediaId, 'target' => $target, 'error_type' => $exception::class],
                        $ipAddress,
                        'conference',
                    );

                    throw $exception;
                }
            });
    }

    private function playableMedia(SwitchAccount $account, string $mediaId): SwitchMedia
    {
        $media = $account->media()->where('id', $mediaId)->first();

        if ($media === null || $media->streamable !== true || ! is_string($media->content_type)
            || ! str_starts_with($media->content_type, 'audio/')) {
            throw ValidationException::withMessages([
                'media_id' => ['Select a streamable audio media asset from this account.'],
            ]);
        }

        return $media;
    }
}
