<?php

namespace App\Domains\Voicemail\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Voicemail\Requests\StoreVoicemailGreetingRequest;
use App\Domains\Voicemail\Resources\VoicemailGreetingResource;
use App\Domains\Voicemail\Services\VoicemailBoxService;
use App\Domains\Voicemail\Services\VoicemailGreetingAudioService;
use App\Domains\Voicemail\Services\VoicemailGreetingMutationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoicemailGreetingController extends Controller
{
    public function store(
        StoreVoicemailGreetingRequest $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailGreetingMutationService $mutation,
    ): VoicemailGreetingResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('update', [$switchVoicemailBox, $switchAccount]);

        return new VoicemailGreetingResource($mutation->store(
            $switchAccount,
            $switchVoicemailBox,
            $user,
            $request->file('audio'),
            $request->validated('name'),
            $request->ip(),
        ));
    }

    public function audio(
        Request $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailGreetingAudioService $audio,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('viewMessages', [$switchVoicemailBox, $switchAccount]);
        $greeting = $switchVoicemailBox->unavailableGreeting()->firstOrFail();
        $range = $request->header('Range');

        if ($range !== null && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range) !== 1) {
            abort(416, 'The requested byte range is invalid.');
        }

        return $audio->stream($switchAccount, $greeting, $user, $range, $request->ip());
    }

    public function destroy(
        Request $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailGreetingMutationService $mutation,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('update', [$switchVoicemailBox, $switchAccount]);
        $greeting = $switchVoicemailBox->unavailableGreeting()->firstOrFail();
        $mutation->detach($switchAccount, $switchVoicemailBox, $greeting, $user, $request->ip());

        return response()->noContent();
    }
}
