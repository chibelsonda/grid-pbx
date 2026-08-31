<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Resources\DashboardOverviewResource;
use App\Domains\Dashboard\Services\DashboardOverviewService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        DashboardOverviewService $dashboard,
    ): DashboardOverviewResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);

        return new DashboardOverviewResource($dashboard->get($switchAccount));
    }
}
