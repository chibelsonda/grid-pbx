<?php

namespace App\Domains\Payments\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Payments\Models\PaymentCustomerProfile;
use App\Domains\Payments\Resources\PaymentCustomerProfileResource;
use App\Domains\Payments\Services\PaymentProfileInventoryService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentCustomerProfileController extends Controller
{
    public function index(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        PaymentProfileInventoryService $profiles,
    ): JsonResponse {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [PaymentCustomerProfile::class, $switchAccount]);

        return ApiResponse::data(
            PaymentCustomerProfileResource::collection($profiles->forAccount($switchAccount))
                ->resolve($request),
        );
    }
}
