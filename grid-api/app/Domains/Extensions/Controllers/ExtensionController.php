<?php

namespace App\Domains\Extensions\Controllers;

use App\Domains\Extensions\Requests\ListExtensionsRequest;
use App\Domains\Extensions\Resources\ExtensionResource;
use App\Domains\Extensions\Services\ExtensionService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExtensionController extends Controller
{
    public function __invoke(
        ListExtensionsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        ExtensionService $extensions,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $validated = $request->validated();

        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'extensions')
            ->first();

        return ExtensionResource::collection($extensions->paginate(
            $switchAccount,
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
