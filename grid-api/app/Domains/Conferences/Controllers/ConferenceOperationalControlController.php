<?php

namespace App\Domains\Conferences\Controllers;

use App\Domains\Conferences\Requests\ConferenceControlRequest;
use App\Domains\Conferences\Services\ConferenceOperationalControlService;
use App\Domains\Conferences\Services\ConferenceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConferenceOperationalControlController extends Controller
{
    public function __invoke(
        ConferenceControlRequest $request,
        string $account,
        string $conference,
        SwitchAccountService $accounts,
        ConferenceService $conferences,
        ConferenceOperationalControlService $controls,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('control', [$model, $switchAccount]);

        return ApiResponse::data(
            $controls->control(
                $switchAccount,
                $model,
                $user,
                $request->validated('action'),
                $request->ip(),
            ),
            Response::HTTP_ACCEPTED,
        );
    }
}
