<?php

namespace App\Domains\Billing\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Billing\Dto\BillingDocumentContent;
use App\Domains\Billing\Enums\BillingDocumentType;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class BillingDocumentDownloadService
{
    public function __construct(private AuditService $audit) {}

    public function streamPdf(
        BillingDocumentContent $document,
        BillingDocumentType $type,
        string $publicId,
        SwitchAccount $account,
        User $actor,
        ?string $ipAddress = null,
    ): ?StreamedResponse {
        $maximumBytes = max(1, (int) config('billing_documents.downloads.maximum_bytes'));

        if ($document->contentType !== 'application/pdf'
            || $document->contentLength < 1
            || $document->contentLength > $maximumBytes) {
            return null;
        }

        $this->audit->record(
            $actor,
            $account,
            $type->downloadAction(),
            'succeeded',
            $publicId,
            ['content_type' => 'application/pdf'],
            $ipAddress,
            $type->value,
        );

        return response()->stream(
            $document->stream,
            200,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s-%s.pdf"',
                    $type->filenamePrefix(),
                    $publicId,
                ),
                'Content-Length' => (string) $document->contentLength,
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
