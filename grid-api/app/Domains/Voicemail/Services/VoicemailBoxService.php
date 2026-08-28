<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VoicemailBoxService
{
    /** @return LengthAwarePaginator<int, SwitchVoicemailBox> */
    public function paginate(SwitchAccount $account, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $account->voicemailBoxes()
            ->with(['extension:extension_id,id,display_name,extension', 'unavailableGreeting'])
            ->withCount($this->messageCounts())
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mailbox', 'like', "%{$search}%")
                        ->orWhere('timezone', 'like', "%{$search}%")
                        ->orWhereHas('extension', function ($query) use ($search): void {
                            $query
                                ->where('display_name', 'like', "%{$search}%")
                                ->orWhere('extension', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw('mailbox IS NULL')
            ->orderBy('mailbox')
            ->orderBy('voicemail_box_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(SwitchAccount $account, string $voicemailBoxId): SwitchVoicemailBox
    {
        return $account->voicemailBoxes()
            ->where('id', $voicemailBoxId)
            ->with(['extension:extension_id,id,display_name,extension', 'unavailableGreeting'])
            ->withCount($this->messageCounts())
            ->firstOrFail();
    }

    /** @return array<int|string, callable|string> */
    private function messageCounts(): array
    {
        return [
            'messages',
            'messages as new_messages_count' => fn ($query) => $query->where('folder', 'new'),
            'messages as saved_messages_count' => fn ($query) => $query->where('folder', 'saved'),
            'messages as deleted_messages_count' => fn ($query) => $query->where('folder', 'deleted'),
        ];
    }
}
