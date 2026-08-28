<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class VoicemailMessageService
{
    /** @return LengthAwarePaginator<int, SwitchVoicemailMessage> */
    public function paginate(
        SwitchVoicemailBox $voicemailBox,
        ?string $search,
        ?string $folder,
        int $perPage,
    ): LengthAwarePaginator {
        return $voicemailBox->messages()
            ->when($folder, fn ($query, string $folder) => $query->where('folder', $folder))
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('caller_id_name', 'like', "%{$search}%")
                        ->orWhere('caller_id_number', 'like', "%{$search}%")
                        ->orWhere('transcription_text', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('voicemail_message_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(SwitchVoicemailBox $voicemailBox, string $messageId): SwitchVoicemailMessage
    {
        return $voicemailBox->messages()->where('id', $messageId)->firstOrFail();
    }

    /**
     * @param  list<string>  $messageIds
     * @return Collection<int, SwitchVoicemailMessage>
     */
    public function findMany(SwitchVoicemailBox $voicemailBox, array $messageIds): Collection
    {
        $messages = $voicemailBox->messages()->whereIn('id', $messageIds)->get();

        if ($messages->count() !== count($messageIds)) {
            throw (new ModelNotFoundException)->setModel(SwitchVoicemailMessage::class, $messageIds);
        }

        return $messages;
    }
}
