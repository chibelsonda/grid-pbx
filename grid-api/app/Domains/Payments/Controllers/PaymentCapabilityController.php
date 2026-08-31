<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Resources\PaymentCapabilityResource;
use App\Domains\Payments\Services\PaymentCapabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentCapabilityController extends Controller
{
    public function __invoke(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        PaymentCapabilityService $capabilities,
    ): PaymentCapabilityResource {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [PaymentAttempt::class, $switchAccount]);

        return new PaymentCapabilityResource($capabilities->get());
    }
}
