<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Requests\RecentMissedCallsRequest;
use App\Domains\Dashboard\Resources\RecentMissedCallsResource;
use App\Domains\Dashboard\Services\RecentMissedCallsService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class RecentMissedCallsController extends Controller
{
    public function __invoke(
        RecentMissedCallsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        RecentMissedCallsService $missedCalls,
    ): RecentMissedCallsResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);

        return new RecentMissedCallsResource(
            $missedCalls->get($switchAccount, $request->activityRange()),
        );
    }
}
