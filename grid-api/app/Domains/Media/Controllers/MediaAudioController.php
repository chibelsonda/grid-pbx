<?php

namespace App\Domains\Media\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Requests\ReplaceMediaAudioRequest;
use App\Domains\Media\Resources\MediaResource;
use App\Domains\Media\Services\MediaAudioService;
use App\Domains\Media\Services\MediaMutationService;
use App\Domains\Media\Services\MediaService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaAudioController extends Controller
{
    public function show(
        Request $request,
        string $account,
        string $media,
        SwitchAccountService $accounts,
        MediaService $mediaService,
        MediaAudioService $audio,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchMedia = $mediaService->find($switchAccount, $media);
        Gate::authorize('view', [$switchMedia, $switchAccount]);
        $range = $request->header('Range');

        if ($range !== null && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range) !== 1) {
            abort(416, 'The requested byte range is invalid.');
        }

        return $audio->stream($switchAccount, $switchMedia, $user, $range, $request->ip());
    }

    public function store(
        ReplaceMediaAudioRequest $request,
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

        return new MediaResource($mutations->replaceAudio(
            $switchAccount,
            $switchMedia,
            $user,
            $request->file('audio'),
            $request->ip(),
        ));
    }
}
