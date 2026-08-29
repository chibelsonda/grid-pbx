<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Requests\UpdateAccountStatusRequest;
use App\Domains\Organizations\Resources\AccountDetailResource;
use App\Domains\Organizations\Services\AccountStatusService;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountStatusController extends Controller
{
    public function update(
        UpdateAccountStatusRequest $request,
        string $account,
        SwitchAccountService $accounts,
        AccountStatusService $status,
    ): AccountDetailResource {
        /** @var User $user */
        $user = $request->user();
        $model = $accounts->findMemberAccessible($user, $account);
        Gate::authorize('setStatus', $model);

        if (! hash_equals($model->name, $request->validated('confirmation'))) {
            throw ValidationException::withMessages([
                'confirmation' => 'Enter the account name exactly to confirm this operation.',
            ]);
        }

        $status->update($model, $user, $request->boolean('enabled'), $request->ip());

        return new AccountDetailResource($accounts->findDetailedAccessible($user, $account));
    }
}
