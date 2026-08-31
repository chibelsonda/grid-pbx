<?php

namespace App\Domains\Services\Controllers;

use App\Domains\Billing\Services\BillingReconciliationService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\Services\Resources\ServiceOverviewResource;
use App\Domains\Services\Services\ServiceOverviewService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceOverviewController extends Controller
{
    public function show(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        ServiceOverviewService $service,
        BillingReconciliationService $reconciliation,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchServiceSummary::class, $switchAccount]);
        $summary = $service->get($switchAccount);

        return $summary === null
            ? ApiResponse::data(null)
            : (new ServiceOverviewResource($summary, $reconciliation->reconcile($summary)))->response();
    }
}
