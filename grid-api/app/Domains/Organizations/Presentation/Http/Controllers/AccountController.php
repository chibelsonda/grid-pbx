<?php

namespace App\Domains\Organizations\Presentation\Http\Controllers;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\Organizations\Application\Queries\ListAccessibleAccounts;
use App\Domains\Organizations\Presentation\Http\Resources\AccountResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __invoke(Request $request, ListAccessibleAccounts $accounts): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AccountResource::collection($accounts->handle($user));
    }
}
