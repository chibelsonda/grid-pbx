<?php

namespace App\Domains\Recordings\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Recordings\Services\RecordingAudioService;
use App\Domains\Recordings\Services\RecordingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordingAudioController extends Controller
{
    public function show(Request $request, string $account, string $recording, SwitchAccountService $accounts, RecordingService $service, RecordingAudioService $audio): StreamedResponse { /** @var User $user */ $user = $request->user(); $switchAccount = $accounts->findAccessible($user, $account); $model = $service->find($switchAccount, $recording); Gate::authorize('view', [$model, $switchAccount]); abort_unless($model->has_audio, 404, 'Recording audio is not available.'); $range = $request->header('Range'); if ($range !== null && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range) !== 1) abort(416, 'The requested byte range is invalid.'); return $audio->stream($switchAccount, $model, $user, $range, $request->boolean('download'), $request->ip()); }
}
