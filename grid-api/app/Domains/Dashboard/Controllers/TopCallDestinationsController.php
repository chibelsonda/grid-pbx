<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Dashboard\Requests\TopCallDestinationsRequest;
use App\Domains\Dashboard\Resources\TopCallDestinationsResource;
use App\Domains\Dashboard\Services\TopCallDestinationsService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class TopCallDestinationsController extends Controller
{
    public function __invoke(
        TopCallDestinationsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        TopCallDestinationsService $destinations,
    ): TopCallDestinationsResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchCallDetailRecord::class, $switchAccount]);

        return new TopCallDestinationsResource(
            $destinations->get($switchAccount, $request->activityRange()),
        );
    }
}
