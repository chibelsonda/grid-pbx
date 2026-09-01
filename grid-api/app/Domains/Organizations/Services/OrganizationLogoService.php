<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class OrganizationLogoService
{
    private const MAX_WIDTH = 512;

    private const MAX_HEIGHT = 256;

    public function __construct(private readonly AuditService $audit) {}

    public function store(
        SwitchAccount $account,
        User $actor,
        UploadedFile $file,
        ?string $ipAddress = null,
    ): Organization {
        $image = $this->sanitize($file);
        $organization = $account->organization()->firstOrFail();
        $path = sprintf('organization-branding/%s/logo-%s.png', $organization->id, Str::uuid());

        if (! Storage::disk('local')->put($path, $image['contents'])) {
            throw new RuntimeException('Unable to store the organization logo.');
        }

        try {
            $previousPath = DB::transaction(function () use (
                $account,
                $actor,
                $image,
                $ipAddress,
                $organization,
                $path,
            ): ?string {
                $locked = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());
                $previousPath = $locked->logo_path;
                $locked->forceFill([
                    'logo_path' => $path,
                    'logo_updated_at' => now(),
                ])->save();

                $this->audit->record(
                    $actor,
                    $account,
                    'organization.logo_updated',
                    'succeeded',
                    $locked->id,
                    [
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'content_type' => 'image/png',
                    ],
                    $ipAddress,
                    'organization',
                );

                return $previousPath;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if (filled($previousPath) && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return $organization->fresh();
    }

    public function destroy(
        SwitchAccount $account,
        User $actor,
        ?string $ipAddress = null,
    ): Organization {
        $organization = $account->organization()->firstOrFail();
        $previousPath = DB::transaction(function () use (
            $account,
            $actor,
            $ipAddress,
            $organization,
        ): ?string {
            $locked = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());
            $previousPath = $locked->logo_path;
            $locked->forceFill([
                'logo_path' => null,
                'logo_updated_at' => null,
            ])->save();

            $this->audit->record(
                $actor,
                $account,
                'organization.logo_removed',
                'succeeded',
                $locked->id,
                [],
                $ipAddress,
                'organization',
            );

            return $previousPath;
        });

        if (filled($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }

        return $organization->fresh();
    }

    public function response(SwitchAccount $account): Response
    {
        $organization = $account->organization()->firstOrFail();
        $path = $organization->logo_path;

        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => sprintf('inline; filename="organization-%s-logo.png"', $organization->id),
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{contents: string, width: int, height: int} */
    private function sanitize(UploadedFile $file): array
    {
        $source = @imagecreatefromstring($file->get());

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Unable to decode the organization logo.');
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $target instanceof GdImage) {
                throw new RuntimeException('Unable to prepare the organization logo.');
            }

            try {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
                imagecopyresampled(
                    $target,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $width,
                    $height,
                );

                ob_start();
                $encoded = imagepng($target, null, 6);
                $contents = ob_get_clean();

                if (! $encoded || ! is_string($contents)) {
                    throw new RuntimeException('Unable to encode the organization logo.');
                }
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }

        return [
            'contents' => $contents,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }
}
