<?php

namespace App\Domains\Extensions\Controllers;

use App\Domains\Extensions\Resources\ExtensionDetailResource;
use App\Domains\Extensions\Services\ExtensionService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExtensionDetailController extends Controller
{
    public function __invoke(
        Request $request,
        string $account,
        string $extension,
        SwitchAccountService $accounts,
        ExtensionService $extensions,
    ): ExtensionDetailResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return new ExtensionDetailResource($extensions->find($switchAccount, $extension));
    }
}
