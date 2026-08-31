<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Requests\CallGeographyRequest;
use App\Domains\Dashboard\Resources\CallGeographyResource;
use App\Domains\Dashboard\Services\CallGeographyService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class CallGeographyController extends Controller
{
    public function __invoke(
        CallGeographyRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallGeographyService $geography,
    ): CallGeographyResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);

        return new CallGeographyResource($geography->get($switchAccount, $request->activityRange()));
    }
}
