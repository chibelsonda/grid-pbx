<?php

namespace App\Domains\Conferences\Resources;

use App\Domains\Conferences\Models\SwitchConference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchConference */
class ConferenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $numbers = fn (string $role): array => $this->relationLoaded('numbers')
            ? $this->numbers->where('role', $role)->pluck('number')->values()->all()
            : [];

        return [
            'id' => $this->id, 'name' => $this->name,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id, 'label' => $this->owner->display_name, 'extension' => $this->owner->extension,
            ]),
            'conference_numbers' => $numbers('conference'), 'member_numbers' => $numbers('member'),
            'moderator_numbers' => $numbers('moderator'), 'member_pin_configured' => $this->member_pin_configured,
            'moderator_pin_configured' => $this->moderator_pin_configured,
            'member_join_muted' => $this->member_join_muted, 'member_join_deaf' => $this->member_join_deaf,
            'member_play_entry_prompt' => $this->member_play_entry_prompt,
            'moderator_join_muted' => $this->moderator_join_muted, 'moderator_join_deaf' => $this->moderator_join_deaf,
            'max_participants' => $this->max_participants, 'language' => $this->language,
            'profile_name' => $this->profile_name, 'caller_controls' => $this->caller_controls,
            'moderator_controls' => $this->moderator_controls, 'play_name' => $this->play_name,
            'play_welcome' => $this->play_welcome, 'require_moderator' => $this->require_moderator,
            'wait_for_moderator' => $this->wait_for_moderator,
            'max_members_media' => $this->mediaResource($this->switch_json['max_members_media'] ?? null),
            'entry_tone' => $this->toneResource($this->switch_json['play_entry_tone'] ?? null),
            'exit_tone' => $this->toneResource($this->switch_json['play_exit_tone'] ?? null),
            'runtime' => ['members' => $this->active_members, 'moderators' => $this->active_moderators, 'duration_seconds' => $this->duration_seconds, 'is_locked' => $this->is_locked],
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{id: string, name: string}|null */
    private function mediaResource(mixed $reference): ?array
    {
        if (! is_string($reference) || $reference === '' || ! $this->relationLoaded('switchAccount') || ! $this->switchAccount->relationLoaded('media')) {
            return null;
        }

        $media = $this->switchAccount->media->firstWhere('switch_resource_id', $reference);

        return $media === null ? null : ['id' => $media->id, 'name' => $media->name];
    }

    /** @return array{mode: string, media: array{id: string, name: string}|null} */
    private function toneResource(mixed $tone): array
    {
        if (is_bool($tone)) {
            return ['mode' => $tone ? 'enabled' : 'disabled', 'media' => null];
        }

        if (is_string($tone) && $tone !== '') {
            $media = $this->mediaResource($tone);

            return ['mode' => $media === null ? 'current_custom' : 'media', 'media' => $media];
        }

        return ['mode' => 'enabled', 'media' => null];
    }
}
