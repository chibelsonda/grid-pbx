<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\AccountSettingsOptionsService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSettingsOptionsController extends Controller
{
    public function __invoke(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        AccountSettingsOptionsService $options,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $options->get($accounts->findMemberAccessible($user, $account)),
        ]);
    }
}
