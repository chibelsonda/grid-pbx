<?php

namespace App\Domains\Extensions\Controllers;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Requests\DeleteExtensionRequest;
use App\Domains\Extensions\Requests\ListExtensionsRequest;
use App\Domains\Extensions\Requests\StoreExtensionRequest;
use App\Domains\Extensions\Requests\UpdateExtensionRequest;
use App\Domains\Extensions\Resources\ExtensionDetailResource;
use App\Domains\Extensions\Resources\ExtensionResource;
use App\Domains\Extensions\Services\ExtensionDeletionPreviewService;
use App\Domains\Extensions\Services\ExtensionDeletionService;
use App\Domains\Extensions\Services\ExtensionOptionsService;
use App\Domains\Extensions\Services\ExtensionProvisioningService;
use App\Domains\Extensions\Services\ExtensionService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExtensionController extends Controller
{
    public function options(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        ExtensionOptionsService $options,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return ApiResponse::data($options->get($switchAccount));
    }

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

    public function store(
        StoreExtensionRequest $request,
        string $account,
        SwitchAccountService $accounts,
        ExtensionProvisioningService $provisioning,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchExtension::class, $switchAccount]);

        return (new ExtensionDetailResource($provisioning->create(
            $switchAccount,
            $user,
            $request->validated(),
            $request->ip(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateExtensionRequest $request,
        string $account,
        string $extension,
        SwitchAccountService $accounts,
        ExtensionService $extensions,
        ExtensionProvisioningService $provisioning,
    ): ExtensionDetailResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchExtension = $extensions->find($switchAccount, $extension);
        Gate::authorize('update', [$switchExtension, $switchAccount]);

        return new ExtensionDetailResource($provisioning->update(
            $switchAccount,
            $switchExtension,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function deletionPreview(
        ListExtensionsRequest $request,
        string $account,
        string $extension,
        SwitchAccountService $accounts,
        ExtensionService $extensions,
        ExtensionDeletionPreviewService $preview,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchExtension = $extensions->find($switchAccount, $extension);
        Gate::authorize('delete', [$switchExtension, $switchAccount]);

        return ApiResponse::data($preview->preview($switchAccount, $switchExtension));
    }

    public function destroy(
        DeleteExtensionRequest $request,
        string $account,
        string $extension,
        SwitchAccountService $accounts,
        ExtensionService $extensions,
        ExtensionDeletionService $deletion,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchExtension = $extensions->find($switchAccount, $extension);
        Gate::authorize('delete', [$switchExtension, $switchAccount]);
        $deletion->delete(
            $switchAccount,
            $switchExtension,
            $user,
            $request->validated('confirmation'),
            $request->ip(),
        );

        return ApiResponse::noContent();
    }
}
