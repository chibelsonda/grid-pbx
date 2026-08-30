<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Requests\OnboardDescendantRequest;
use App\Domains\Organizations\Services\DescendantOnboardingService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DescendantOnboardingController extends Controller
{
    public function index(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        DescendantOnboardingService $onboarding,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('onboardDescendant', $model);

        return ApiResponse::data($onboarding->candidates($model, $user));
    }

    public function store(
        OnboardDescendantRequest $request,
        string $account,
        SwitchAccountService $accounts,
        DescendantOnboardingService $onboarding,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('onboardDescendant', $model);
        $data = $request->validated();

        return ApiResponse::data($onboarding->onboard(
            $model,
            $user,
            $data['reference'],
            $data['confirmation'],
            $request->ip(),
        ), 201);
    }
}
