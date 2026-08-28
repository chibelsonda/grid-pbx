<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailMessageGateway;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoicemailAudioService
{
    public function __construct(
        private readonly SwitchVoicemailMessageGateway $gateway,
        private readonly AuditService $audit,
    ) {}

    public function stream(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        SwitchVoicemailMessage $message,
        User $actor,
        ?string $range,
        bool $download,
        ?string $ipAddress = null,
    ): StreamedResponse {
        $audio = $this->gateway->audio(
            $account,
            $voicemailBox->switch_resource_id,
            $message->switch_resource_id,
            $range,
        );
        $contentType = str_starts_with($audio->contentType, 'audio/')
            ? $audio->contentType
            : 'application/octet-stream';
        $extension = match ($contentType) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4' => 'mp4',
            'audio/wav', 'audio/x-wav' => 'wav',
            default => 'bin',
        };
        $disposition = $download ? 'attachment' : 'inline';
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => sprintf(
                '%s; filename="voicemail-%s.%s"',
                $disposition,
                $message->getKey(),
                $extension,
            ),
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
                $download ? 'voicemail_message.downloaded' : 'voicemail_message.streamed',
                'succeeded',
                $message->switch_resource_id,
                ['voicemail_box_id' => $voicemailBox->getKey()],
                $ipAddress,
                'voicemail_message',
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
