<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Requests\CallActivityTrendRequest;
use App\Domains\Dashboard\Resources\CallActivityTrendResource;
use App\Domains\Dashboard\Services\CallActivityTrendService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class CallActivityTrendController extends Controller
{
    public function __invoke(
        CallActivityTrendRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallActivityTrendService $trends,
    ): CallActivityTrendResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);

        return new CallActivityTrendResource($trends->get($switchAccount, $request->activityRange()));
    }
}
