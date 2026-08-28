<?php

namespace App\Domains\Media\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class MediaMutationService
{
    public function __construct(
        private readonly SwitchMediaGateway $gateway,
        private readonly MediaProjectionService $projection,
        private readonly MediaDependencyService $dependencies,
        private readonly CallflowReferenceResolver $callflowReferences,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        UploadedFile $audio,
        ?string $ipAddress = null,
    ): SwitchMedia {
        $resourceId = null;

        try {
            $created = $this->gateway->create($account, $data);
            $resourceId = $this->requiredResourceId($created);
            $uploaded = $this->upload($account, $resourceId, $audio);

            return DB::transaction(function () use ($account, $actor, $audio, $ipAddress, $uploaded): SwitchMedia {
                $media = $this->projection->project($account, $uploaded);
                $this->callflowReferences->refresh($account);
                $this->audit->record(
                    $actor,
                    $account,
                    'media.created',
                    'succeeded',
                    $media->switch_resource_id,
                    [
                        'content_type' => $media->content_type,
                        'content_length' => $media->content_length,
                        'original_name' => $audio->getClientOriginalName(),
                    ],
                    $ipAddress,
                    'media',
                );

                return $media;
            });
        } catch (Throwable $exception) {
            if ($resourceId !== null) {
                $this->safelyDelete($account, $resourceId);
            }

            $this->audit->record(
                $actor,
                $account,
                'media.create_failed',
                'failed',
                $resourceId,
                ['error_type' => $exception::class],
                $ipAddress,
                'media',
            );

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchMedia $media,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchMedia {
        $snapshot = $this->gateway->update($account, $media->switch_resource_id, [
            ...$data,
            'media_source' => $media->media_source ?? 'upload',
        ]);

        return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchMedia {
            $projected = $this->projection->project($account, $snapshot);
            $this->callflowReferences->refresh($account);
            $this->audit->record(
                $actor,
                $account,
                'media.updated',
                'succeeded',
                $projected->switch_resource_id,
                [],
                $ipAddress,
                'media',
            );

            return $projected;
        });
    }

    public function replaceAudio(
        SwitchAccount $account,
        SwitchMedia $media,
        User $actor,
        UploadedFile $audio,
        ?string $ipAddress = null,
    ): SwitchMedia {
        $snapshot = $this->upload($account, $media->switch_resource_id, $audio);

        return DB::transaction(function () use ($account, $actor, $audio, $ipAddress, $snapshot): SwitchMedia {
            $projected = $this->projection->project($account, $snapshot);
            $this->audit->record(
                $actor,
                $account,
                'media.audio_replaced',
                'succeeded',
                $projected->switch_resource_id,
                ['original_name' => $audio->getClientOriginalName()],
                $ipAddress,
                'media',
            );

            return $projected;
        });
    }

    public function delete(
        SwitchAccount $account,
        SwitchMedia $media,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        $dependencies = $this->dependencies->summary($account, $media);

        if (! $dependencies['can_delete']) {
            throw new ConflictHttpException('This media is still assigned. Remove its dependencies before deleting it.');
        }

        $this->gateway->delete($account, $media->switch_resource_id);

        DB::transaction(function () use ($account, $media, $actor, $ipAddress): void {
            $media->delete();
            $this->audit->record(
                $actor,
                $account,
                'media.deleted',
                'succeeded',
                $media->switch_resource_id,
                [],
                $ipAddress,
                'media',
            );
        });
    }

    /** @return array<string, mixed> */
    private function upload(SwitchAccount $account, string $resourceId, UploadedFile $audio): array
    {
        $realPath = $audio->getRealPath();

        if ($realPath === false) {
            throw new RuntimeException('Unable to read the uploaded media audio.');
        }

        $handle = fopen($realPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded media audio.');
        }

        return $this->gateway->upload(
            $account,
            $resourceId,
            Utils::streamFor($handle),
            (string) $audio->getMimeType(),
            (int) $audio->getSize(),
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function requiredResourceId(array $snapshot): string
    {
        $resourceId = $snapshot['id'] ?? null;

        if (! is_string($resourceId) || $resourceId === '') {
            throw new RuntimeException('Switch media response is missing its resource identifier.');
        }

        return $resourceId;
    }

    private function safelyDelete(SwitchAccount $account, string $resourceId): void
    {
        try {
            $this->gateway->delete($account, $resourceId);
        } catch (Throwable) {
        }
    }
}
