<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Resources\AccountResource;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __invoke(Request $request, SwitchAccountService $accounts): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AccountResource::collection($accounts->accessibleTo($user));
    }
}
