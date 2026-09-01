<?php

namespace App\Domains\Conferences\Controllers;

use App\Domains\Conferences\Requests\ConferenceBulkParticipantControlRequest;
use App\Domains\Conferences\Requests\ConferenceParticipantControlRequest;
use App\Domains\Conferences\Services\ConferenceParticipantService;
use App\Domains\Conferences\Services\ConferenceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConferenceParticipantController extends Controller
{
    public function index(
        Request $request,
        string $account,
        string $conference,
        SwitchAccountService $accounts,
        ConferenceService $conferences,
        ConferenceParticipantService $participants,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('view', [$model, $switchAccount]);

        return ApiResponse::data($participants->participants($switchAccount, $model));
    }

    public function control(
        ConferenceParticipantControlRequest $request,
        string $account,
        string $conference,
        SwitchAccountService $accounts,
        ConferenceService $conferences,
        ConferenceParticipantService $participants,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('control', [$model, $switchAccount]);

        return ApiResponse::data(
            $participants->control(
                $switchAccount,
                $model,
                $user,
                $request->validated('participant_id'),
                $request->validated('action'),
                $request->ip(),
            ),
            Response::HTTP_ACCEPTED,
        );
    }

    public function controlAll(
        ConferenceBulkParticipantControlRequest $request,
        string $account,
        string $conference,
        SwitchAccountService $accounts,
        ConferenceService $conferences,
        ConferenceParticipantService $participants,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('control', [$model, $switchAccount]);

        return ApiResponse::data(
            $participants->controlAll(
                $switchAccount,
                $model,
                $user,
                $request->validated('action'),
                $request->integer('expected_participant_count'),
                $request->integer('expected_target_count'),
                $request->ip(),
            ),
            Response::HTTP_ACCEPTED,
        );
    }
}
