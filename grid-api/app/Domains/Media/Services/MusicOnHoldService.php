<?php

namespace App\Domains\Media\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnexpectedValueException;

class MusicOnHoldService
{
    public function __construct(
        private readonly SwitchMediaGateway $gateway,
        private readonly AuditService $audit,
    ) {}

    public function selected(SwitchAccount $account): ?SwitchMedia
    {
        return $account->musicOnHoldMedia()->first();
    }

    public function update(
        SwitchAccount $account,
        User $actor,
        ?string $mediaId,
        ?string $ipAddress = null,
    ): ?SwitchMedia {
        $media = $mediaId === null
            ? null
            : $account->media()->where('id', $mediaId)->firstOrFail();
        $previousResourceId = $account->musicOnHoldMedia()->value('switch_resource_id');
        $resourceId = $media?->switch_resource_id;
        $updatedResourceId = $this->gateway->updateAccountMusicOnHold($account, $resourceId);

        if ($updatedResourceId !== $resourceId) {
            throw new UnexpectedValueException('Switch returned an unexpected music-on-hold assignment.');
        }

        try {
            DB::transaction(function () use ($account, $actor, $media, $ipAddress): void {
                $account->update(['music_on_hold_media_id' => $media?->getKey()]);
                $this->audit->record(
                    $actor,
                    $account,
                    'music_on_hold.updated',
                    'succeeded',
                    $media?->switch_resource_id,
                    ['media_id' => $media?->id],
                    $ipAddress,
                    'account_music_on_hold',
                );
            });
        } catch (Throwable $exception) {
            try {
                $this->gateway->updateAccountMusicOnHold($account, $previousResourceId);
            } catch (Throwable) {
            }

            throw $exception;
        }

        return $media;
    }
}
