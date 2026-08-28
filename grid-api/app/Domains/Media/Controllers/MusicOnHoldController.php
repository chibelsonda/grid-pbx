<?php

namespace App\Domains\Media\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Media\Requests\UpdateMusicOnHoldRequest;
use App\Domains\Media\Resources\MediaResource;
use App\Domains\Media\Services\MusicOnHoldService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MusicOnHoldController extends Controller
{
    public function show(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        MusicOnHoldService $musicOnHold,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchMedia::class, $switchAccount]);
        $media = $musicOnHold->selected($switchAccount);

        return response()->json(['data' => [
            'media' => $media === null ? null : (new MediaResource($media))->resolve($request),
        ]]);
    }

    public function update(
        UpdateMusicOnHoldRequest $request,
        string $account,
        SwitchAccountService $accounts,
        MusicOnHoldService $musicOnHold,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('updateMusicOnHold', [SwitchMedia::class, $switchAccount]);
        $media = $musicOnHold->update(
            $switchAccount,
            $user,
            $request->validated('media_id'),
            $request->ip(),
        );

        return response()->json(['data' => [
            'media' => $media === null ? null : (new MediaResource($media))->resolve($request),
        ]]);
    }
}
