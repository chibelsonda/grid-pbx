<?php

namespace App\Domains\Media\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaAudioService
{
    public function __construct(
        private readonly SwitchMediaGateway $gateway,
        private readonly AuditService $audit,
    ) {}

    public function stream(
        SwitchAccount $account,
        SwitchMedia $media,
        User $actor,
        ?string $range,
        ?string $ipAddress = null,
    ): StreamedResponse {
        $audio = $this->gateway->audio($account, $media->switch_resource_id, $range);
        $contentType = str_starts_with($audio->contentType, 'audio/')
            ? $audio->contentType
            : 'application/octet-stream';
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => sprintf('inline; filename="media-%s"', $media->id),
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($audio->contentLength !== null) {
            $headers['Content-Length'] = (string) $audio->contentLength;
        }

        if ($audio->contentRange !== null) {
            $headers['Content-Range'] = $audio->contentRange;
        }

        if ($range === null || str_starts_with($range, 'bytes=0-')) {
            $this->audit->record(
                $actor,
                $account,
                'media.streamed',
                'succeeded',
                $media->switch_resource_id,
                [],
                $ipAddress,
                'media',
            );
        }

        return response()->stream(function () use ($audio): void {
            try {
                while (! $audio->stream->eof()) {
                    echo $audio->stream->read(8192);
                }
            } finally {
                $audio->stream->close();
            }
        }, $audio->statusCode === 206 ? 206 : 200, $headers);
    }
}
