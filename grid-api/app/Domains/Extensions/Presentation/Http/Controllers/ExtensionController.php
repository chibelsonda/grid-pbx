<?php

namespace App\Domains\Extensions\Presentation\Http\Controllers;

use App\Domains\Extensions\Application\Queries\ListExtensions;
use App\Domains\Extensions\Presentation\Http\Requests\ListExtensionsRequest;
use App\Domains\Extensions\Presentation\Http\Resources\ExtensionResource;
use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncCheckpoint;
use App\Domains\Organizations\Application\Queries\FindAccessibleKazooAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExtensionController extends Controller
{
    public function __invoke(
        ListExtensionsRequest $request,
        string $account,
        FindAccessibleKazooAccount $findAccount,
        ListExtensions $extensions,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $kazooAccount = $findAccount->handle($user, $account);
        $validated = $request->validated();

        $checkpoint = SyncCheckpoint::query()
            ->where('kazoo_account_id', $kazooAccount->getKey())
            ->where('resource_type', 'extensions')
            ->first();

        return ExtensionResource::collection($extensions->handle(
            $kazooAccount,
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 25),
        ))->additional([
            'meta' => [
                'sync' => [
                    'status' => $checkpoint?->status->value ?? 'stale',
                    'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
                    'error_message' => $checkpoint?->error_message,
                ],
            ],
        ]);
    }
}
