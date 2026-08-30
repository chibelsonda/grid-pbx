<?php

namespace App\Domains\Conferences\Controllers;

use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Conferences\Requests\ListConferencesRequest;
use App\Domains\Conferences\Requests\SaveConferenceRequest;
use App\Domains\Conferences\Resources\ConferenceResource;
use App\Domains\Conferences\Services\ConferenceMutationService;
use App\Domains\Conferences\Services\ConferenceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConferenceController extends Controller
{
    public function index(ListConferencesRequest $request, string $account, SwitchAccountService $accounts, ConferenceService $conferences): AnonymousResourceCollection
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchConference::class, $switchAccount]);
        $validated = $request->validated();

        return ConferenceResource::collection($conferences->paginate($switchAccount, $validated, (int) ($validated['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, ConferenceService $conferences): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchConference::class, $switchAccount]);

        return ApiResponse::data($conferences->options($switchAccount));
    }

    public function show(Request $request, string $account, string $conference, SwitchAccountService $accounts, ConferenceService $conferences): ConferenceResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('view', [$model, $switchAccount]);

        return new ConferenceResource($model);
    }

    public function store(SaveConferenceRequest $request, string $account, SwitchAccountService $accounts, ConferenceMutationService $mutations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchConference::class, $switchAccount]);

        return (new ConferenceResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveConferenceRequest $request, string $account, string $conference, SwitchAccountService $accounts, ConferenceService $conferences, ConferenceMutationService $mutations): ConferenceResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('update', [$model, $switchAccount]);

        return new ConferenceResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $conference, SwitchAccountService $accounts, ConferenceService $conferences, ConferenceMutationService $mutations): Response
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
