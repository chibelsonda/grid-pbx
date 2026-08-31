<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Requests\CallQualityRequest;
use App\Domains\Dashboard\Resources\CallQualityResource;
use App\Domains\Dashboard\Services\CallQualityService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class CallQualityController extends Controller
{
    public function __invoke(
        CallQualityRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallQualityService $quality,
    ): CallQualityResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);

        return new CallQualityResource(
            $quality->get($switchAccount, $request->activityRange()),
        );
    }
}
