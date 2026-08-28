<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailGreetingGateway;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoicemailGreetingAudioService
{
    public function __construct(
        private readonly SwitchVoicemailGreetingGateway $gateway,
        private readonly AuditService $audit,
    ) {}

    public function stream(
        SwitchAccount $account,
        SwitchVoicemailGreeting $greeting,
        User $actor,
        ?string $range,
        ?string $ipAddress = null,
    ): StreamedResponse {
        $audio = $this->gateway->audio($account, $greeting->switch_resource_id, $range);
        $contentType = str_starts_with($audio->contentType, 'audio/')
            ? $audio->contentType
            : 'application/octet-stream';
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => sprintf('inline; filename="voicemail-greeting-%s"', $greeting->getKey()),
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
                'voicemail_greeting.streamed',
                'succeeded',
                $greeting->switch_resource_id,
                ['voicemail_box_id' => $greeting->switch_voicemail_box_id],
                $ipAddress,
                'voicemail_greeting',
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
