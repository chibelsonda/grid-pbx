<?php

namespace App\Domains\Voicemail\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Voicemail\Enums\VoicemailMessageFolder;
use App\Domains\Voicemail\Requests\BulkChangeVoicemailMessageFolderRequest;
use App\Domains\Voicemail\Requests\ChangeVoicemailMessageFolderRequest;
use App\Domains\Voicemail\Requests\ListVoicemailMessagesRequest;
use App\Domains\Voicemail\Resources\VoicemailMessageResource;
use App\Domains\Voicemail\Services\VoicemailAudioService;
use App\Domains\Voicemail\Services\VoicemailBoxService;
use App\Domains\Voicemail\Services\VoicemailMessageMutationService;
use App\Domains\Voicemail\Services\VoicemailMessageService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Http\Requests\StreamBinaryResourceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoicemailMessageController extends Controller
{
    public function index(
        ListVoicemailMessagesRequest $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailMessageService $messages,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('viewMessages', [$switchVoicemailBox, $switchAccount]);
        $validated = $request->validated();

        return VoicemailMessageResource::collection($messages->paginate(
            $switchVoicemailBox,
            $validated['search'] ?? null,
            $validated['folder'] ?? null,
            (int) ($validated['per_page'] ?? 25),
        ));
    }

    public function audio(
        StreamBinaryResourceRequest $request,
        string $account,
        string $voicemailBox,
        string $message,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailMessageService $messages,
        VoicemailAudioService $audio,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        $switchMessage = $messages->find($switchVoicemailBox, $message);
        Gate::authorize('view', [$switchMessage, $switchVoicemailBox, $switchAccount]);
        $range = $request->header('Range');

        if ($range !== null && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range) !== 1) {
            abort(416, 'The requested byte range is invalid.');
        }

        return $audio->stream(
            $switchAccount,
            $switchVoicemailBox,
            $switchMessage,
            $user,
            $range,
            $request->boolean('download'),
            $request->ip(),
        );
    }

    public function update(
        ChangeVoicemailMessageFolderRequest $request,
        string $account,
        string $voicemailBox,
        string $message,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailMessageService $messages,
        VoicemailMessageMutationService $mutation,
    ): VoicemailMessageResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        $switchMessage = $messages->find($switchVoicemailBox, $message);
        Gate::authorize('update', [$switchMessage, $switchVoicemailBox, $switchAccount]);
        $folder = VoicemailMessageFolder::from($request->validated('folder'));

        return new VoicemailMessageResource($mutation->changeFolder(
            $switchAccount,
            $switchVoicemailBox,
            $switchMessage,
            $folder,
            $user,
            $request->ip(),
        ));
    }

    public function bulkUpdate(
        BulkChangeVoicemailMessageFolderRequest $request,
        string $account,
        string $voicemailBox,
        SwitchAccountService $accounts,
        VoicemailBoxService $voicemailBoxes,
        VoicemailMessageService $messages,
        VoicemailMessageMutationService $mutation,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchVoicemailBox = $voicemailBoxes->find($switchAccount, $voicemailBox);
        Gate::authorize('update', [$switchVoicemailBox, $switchAccount]);
        /** @var list<string> $messageIds */
        $messageIds = $request->validated('message_ids');
        $folder = VoicemailMessageFolder::from($request->validated('folder'));
        $selectedMessages = $messages->findMany($switchVoicemailBox, $messageIds);

        return ApiResponse::data($mutation->changeFolders(
            $switchAccount,
            $switchVoicemailBox,
            $selectedMessages,
            $folder,
            $user,
            $request->ip(),
        ) + ['folder' => $folder->value]);
    }
}
