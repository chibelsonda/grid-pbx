<?php

namespace App\Domains\CallDetailRecords\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallDetailRecords\Requests\ListCallDetailRecordsRequest;
use App\Domains\CallDetailRecords\Resources\CallDetailRecordResource;
use App\Domains\CallDetailRecords\Services\CallDetailRecordService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CallDetailRecordController extends Controller
{
    public function index(
        ListCallDetailRecordsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallDetailRecordService $records,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);
        $validated = $request->validated();
        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'call_detail_records')
            ->first();

        return CallDetailRecordResource::collection($records->paginate(
            $switchAccount,
            $validated,
            (int) ($validated['per_page'] ?? 25),
        ))->additional(['meta' => [
            'sync' => [
                'status' => $checkpoint?->status->value ?? 'stale',
                'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
                'error_message' => $checkpoint?->publicErrorMessage(),
            ],
            'import_window_days' => (int) config('switch.cdr_import_window_days'),
        ]]);
    }

    public function show(
        Request $request,
        string $account,
        string $callDetailRecord,
        SwitchAccountService $accounts,
        CallDetailRecordService $records,
    ): CallDetailRecordResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $record = $records->find($switchAccount, $callDetailRecord);
        Gate::authorize('view', [$record, $switchAccount]);

        return new CallDetailRecordResource($record);
    }
}
