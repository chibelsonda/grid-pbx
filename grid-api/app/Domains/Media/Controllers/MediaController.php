<?php

namespace App\Domains\Media\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Media\Requests\ListMediaRequest;
use App\Domains\Media\Requests\StoreMediaRequest;
use App\Domains\Media\Requests\UpdateMediaRequest;
use App\Domains\Media\Resources\MediaResource;
use App\Domains\Media\Services\MediaMutationService;
use App\Domains\Media\Services\MediaService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MediaController extends Controller
{
    public function index(
        ListMediaRequest $request,
        string $account,
        SwitchAccountService $accounts,
        MediaService $media,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchMedia::class, $switchAccount]);
        $validated = $request->validated();
        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'media')
            ->first();

        return MediaResource::collection($media->paginate(
            $switchAccount,
            $validated,
            (int) ($validated['per_page'] ?? 25),
        ))->additional(['meta' => ['sync' => [
            'status' => $checkpoint?->status->value ?? 'stale',
            'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
            'error_message' => $checkpoint?->error_message,
            'scope' => 'media_projection',
        ]]]);
    }

    public function show(
        Request $request,
        string $account,
        string $media,
        SwitchAccountService $accounts,
        MediaService $mediaService,
    ): MediaResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchMedia = $mediaService->find($switchAccount, $media, true);
        Gate::authorize('view', [$switchMedia, $switchAccount]);

        return new MediaResource($switchMedia);
    }

    public function store(
        StoreMediaRequest $request,
        string $account,
        SwitchAccountService $accounts,
        MediaMutationService $mutations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchMedia::class, $switchAccount]);

        return (new MediaResource($mutations->create(
            $switchAccount,
            $user,
            $request->safe()->except('audio'),
            $request->file('audio'),
            $request->ip(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateMediaRequest $request,
        string $account,
        string $media,
        SwitchAccountService $accounts,
        MediaService $mediaService,
        MediaMutationService $mutations,
    ): MediaResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchMedia = $mediaService->find($switchAccount, $media);
        Gate::authorize('update', [$switchMedia, $switchAccount]);

        return new MediaResource($mutations->update(
            $switchAccount,
            $switchMedia,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        string $media,
        SwitchAccountService $accounts,
        MediaService $mediaService,
        MediaMutationService $mutations,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchMedia = $mediaService->find($switchAccount, $media);
        Gate::authorize('delete', [$switchMedia, $switchAccount]);
        $mutations->delete($switchAccount, $switchMedia, $user, $request->ip());

        return response()->noContent();
    }
}
