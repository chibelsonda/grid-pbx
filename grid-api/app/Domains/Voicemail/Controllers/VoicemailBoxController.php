<?php

namespace App\Domains\Voicemail\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Requests\ListVoicemailBoxesRequest;
use App\Domains\Voicemail\Requests\SaveVoicemailBoxRequest;
use App\Domains\Voicemail\Resources\VoicemailBoxResource;
use App\Domains\Voicemail\Services\VoicemailBoxMutationService;
use App\Domains\Voicemail\Services\VoicemailBoxOptionsService;
use App\Domains\Voicemail\Services\VoicemailBoxService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VoicemailBoxController extends Controller
{
    public function options(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        VoicemailBoxOptionsService $options,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return ApiResponse::data($options->get($switchAccount));
    }

    public function index(
        ListVoicemailBoxesRequest $request,
        string $account,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $validated = $request->validated();
        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'extensions')
            ->first();

        return VoicemailBoxResource::collection($voicemailBoxes->paginate(
            $switchAccount,
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 25),
        ))->additional([
            'meta' => [
                'sync' => [
                    'status' => $checkpoint?->status->value ?? 'stale',
                    'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
                    'error_message' => $checkpoint?->publicErrorMessage(),
                ],
            ],
        ]);
    }

    public function show(
        Request $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
    ): VoicemailBoxResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return new VoicemailBoxResource($voicemailBoxes->find($switchAccount, $voicemailBox));
    }

    public function store(
        SaveVoicemailBoxRequest $request,
        string $account,
        SwitchAccountService $accounts,
        VoicemailBoxMutationService $mutations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchVoicemailBox::class, $switchAccount]);

        return (new VoicemailBoxResource($mutations->create(
            $switchAccount,
            $user,
            $request->validated(),
            $request->ip(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        SaveVoicemailBoxRequest $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailBoxMutationService $mutations,
    ): VoicemailBoxResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('update', [$switchVoicemailBox, $switchAccount]);

        return new VoicemailBoxResource($mutations->update(
            $switchAccount,
            $switchVoicemailBox,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailBoxMutationService $mutations,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('delete', [$switchVoicemailBox, $switchAccount]);
        $mutations->delete($switchAccount, $switchVoicemailBox, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
