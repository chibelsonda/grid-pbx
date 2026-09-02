<?php

namespace App\Domains\IdentityAccess\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\IdentityAccess\Requests\UpdatePasswordRequest;
use App\Domains\IdentityAccess\Services\PasswordService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class PasswordController extends Controller
{
    public function update(
        UpdatePasswordRequest $request,
        PasswordService $passwords,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $passwords->update(
            $user,
            $request->validated('password'),
            $request->ip(),
        );

        return ApiResponse::noContent();
    }
}
