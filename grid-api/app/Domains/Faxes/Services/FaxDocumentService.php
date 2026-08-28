<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Faxes\Contracts\SwitchFaxGateway;
use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaxDocumentService
{
    public function __construct(private readonly SwitchFaxGateway $gateway, private readonly AuditService $audit) {}
    public function stream(SwitchAccount $account, SwitchFax $fax, User $actor, ?string $range, bool $download, ?string $ip = null): StreamedResponse
    {
        $document = $this->gateway->document($account, $fax->folder, $fax->switch_resource_id, $range); $allowed = ['application/pdf', 'image/tiff']; $type = in_array($document->contentType, $allowed, true) ? $document->contentType : 'application/octet-stream'; $extension = $type === 'application/pdf' ? 'pdf' : ($type === 'image/tiff' ? 'tiff' : 'bin');
        $headers = ['Accept-Ranges' => 'bytes', 'Cache-Control' => 'private, no-store', 'Content-Disposition' => sprintf('%s; filename="fax-%s.%s"', $download ? 'attachment' : 'inline', $fax->id, $extension), 'Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff']; if ($document->contentLength !== null) $headers['Content-Length'] = (string) $document->contentLength; if ($document->contentRange !== null) $headers['Content-Range'] = $document->contentRange;
        if ($range === null || str_starts_with($range, 'bytes=0-')) $this->audit->record($actor, $account, $download ? 'fax_document.downloaded' : 'fax_document.viewed', 'succeeded', $fax->switch_resource_id, ['folder' => $fax->folder], $ip, 'fax');
        return response()->stream(function () use ($document): void { try { while (! $document->stream->eof()) echo $document->stream->read(8192); } finally { $document->stream->close(); } }, $document->statusCode === 206 ? 206 : 200, $headers);
    }
}
