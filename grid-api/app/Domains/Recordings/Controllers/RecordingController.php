<?php

namespace App\Domains\Recordings\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\Recordings\Requests\ListRecordingsRequest;
use App\Domains\Recordings\Resources\RecordingResource;
use App\Domains\Recordings\Services\RecordingService;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RecordingController extends Controller
{
    public function index(ListRecordingsRequest $request, string $account, SwitchAccountService $accounts, RecordingService $service): AnonymousResourceCollection
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchRecording::class, $switchAccount]);
        $data = $request->validated();
        $resource = RecordingResource::collection($service->paginate($switchAccount, $data, (int) ($data['per_page'] ?? 25)));
        $checkpoint = SyncCheckpoint::query()->where('switch_account_id', $switchAccount->getKey())->where('resource_type', 'recordings')->first();

        return $resource->additional(['meta' => ['sync' => ['status' => $checkpoint?->status?->value ?? ProjectionStatus::Stale->value, 'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(), 'error_message' => $checkpoint?->publicErrorMessage()], 'import_window_days' => (int) config('switch.recording_import_window_days')]]);
    }

    public function show(Request $request, string $account, string $recording, SwitchAccountService $accounts, RecordingService $service): RecordingResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $recording);
        Gate::authorize('view', [$model, $switchAccount]);

        return new RecordingResource($model);
    }
}
