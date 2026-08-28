<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;

class MediaDependencyService
{
    /** @return array{music_on_hold: int, voicemail_greetings: int, callflows: int, total: int, can_delete: bool} */
    public function summary(SwitchAccount $account, SwitchMedia $media): array
    {
        $musicOnHold = $account->music_on_hold_media_id === $media->getKey() ? 1 : 0;
        $voicemailGreetings = $account->voicemailGreetings()
            ->where('switch_resource_id', $media->switch_resource_id)
            ->count();
        $callflows = $account->callflows()
            ->whereJsonContains('modules', 'play')
            ->get(['callflow_id', 'switch_json'])
            ->filter(fn ($callflow): bool => $this->flowReferences(
                $callflow->switch_json['flow'] ?? null,
                $media->switch_resource_id,
            ))
            ->count();
        $total = $musicOnHold + $voicemailGreetings + $callflows;

        return [
            'music_on_hold' => $musicOnHold,
            'voicemail_greetings' => $voicemailGreetings,
            'callflows' => $callflows,
            'total' => $total,
            'can_delete' => $total === 0,
        ];
    }

    private function flowReferences(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) {
            return false;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        if (($node['module'] ?? null) === 'play' && ($data['id'] ?? null) === $resourceId) {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->flowReferences($child, $resourceId)) {
                return true;
            }
        }

        return false;
    }
}
