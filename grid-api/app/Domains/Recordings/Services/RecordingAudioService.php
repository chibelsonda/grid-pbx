<?php

namespace App\Domains\Recordings\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Contracts\SwitchRecordingGateway;
use App\Domains\Recordings\Models\SwitchRecording;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingAudioService
{
    public function __construct(private readonly SwitchRecordingGateway $gateway, private readonly AuditService $audit) {}
    public function stream(SwitchAccount $account, SwitchRecording $recording, User $actor, ?string $range, bool $download, ?string $ip = null): StreamedResponse
    {
        $audio = $this->gateway->audio($account, $recording->switch_resource_id, $range); $type = str_starts_with($audio->contentType, 'audio/') ? $audio->contentType : 'application/octet-stream';
        $extension = $type === 'audio/mpeg' ? 'mp3' : 'bin'; $headers = ['Accept-Ranges' => 'bytes', 'Cache-Control' => 'private, no-store', 'Content-Disposition' => sprintf('%s; filename="recording-%s.%s"', $download ? 'attachment' : 'inline', $recording->id, $extension), 'Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff'];
        if ($audio->contentLength !== null) $headers['Content-Length'] = (string) $audio->contentLength; if ($audio->contentRange !== null) $headers['Content-Range'] = $audio->contentRange;
        if ($range === null || str_starts_with($range, 'bytes=0-')) $this->audit->record($actor, $account, $download ? 'recording.downloaded' : 'recording.played', 'succeeded', $recording->switch_resource_id, [], $ip, 'recording');
        return response()->stream(function () use ($audio): void { try { while (! $audio->stream->eof()) echo $audio->stream->read(8192); } finally { $audio->stream->close(); } }, $audio->statusCode === 206 ? 206 : 200, $headers);
    }
}
