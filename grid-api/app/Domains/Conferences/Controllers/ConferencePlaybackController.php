<?php

namespace App\Domains\Conferences\Controllers;

use App\Domains\Conferences\Requests\ConferencePlaybackRequest;
use App\Domains\Conferences\Services\ConferencePlaybackService;
use App\Domains\Conferences\Services\ConferenceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConferencePlaybackController extends Controller
{
    public function __invoke(
        ConferencePlaybackRequest $request,
        string $account,
        string $conference,
        SwitchAccountService $accounts,
        ConferenceService $conferences,
        ConferencePlaybackService $playback,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $conferences->find($switchAccount, $conference);
        Gate::authorize('control', [$model, $switchAccount]);

        return ApiResponse::data(
            $playback->play(
                $switchAccount,
                $model,
                $user,
                $request->validated('media_id'),
                $request->validated('participant_id'),
                $request->ip(),
            ),
            Response::HTTP_ACCEPTED,
        );
    }
}
