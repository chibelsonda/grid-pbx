<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MediaService
{
    public function __construct(private readonly MediaDependencyService $dependencies) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SwitchMedia>
     */
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        $musicOnHoldMediaId = $account->music_on_hold_media_id;

        return $account->media()
            ->select([
                'media_id', 'id', 'switch_account_id', 'name', 'description', 'language',
                'media_source', 'content_type', 'content_length', 'prompt_id', 'streamable',
                'last_synced_at', 'sync_status', 'created_at', 'updated_at',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('language', 'like', "%{$search}%");
                });
            })
            ->when($filters['media_source'] ?? null, fn ($query, string $source) => $query->where('media_source', $source))
            ->orderBy('name')
            ->orderBy('media_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (SwitchMedia $media) use ($musicOnHoldMediaId): SwitchMedia {
                $media->setAttribute('is_music_on_hold', $media->getKey() === $musicOnHoldMediaId);

                return $media;
            });
    }

    public function find(SwitchAccount $account, string $mediaId, bool $withDependencies = false): SwitchMedia
    {
        $media = $account->media()->where('id', $mediaId)->firstOrFail();
        $media->setAttribute('is_music_on_hold', $media->getKey() === $account->music_on_hold_media_id);

        if ($withDependencies) {
            $media->setAttribute('dependency_summary', $this->dependencies->summary($account, $media));
        }

        return $media;
    }
}
