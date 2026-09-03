<?php

namespace App\Domains\IdentityAccess\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\IdentityAccess\Requests\ForgotPasswordRequest;
use App\Domains\IdentityAccess\Requests\ResetPasswordRequest;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PasswordResetController extends Controller
{
    private const string LINK_RESPONSE_MESSAGE =
        'If an account exists for that email address, a password reset link has been sent.';

    private const string INVALID_TOKEN_MESSAGE =
        'This password reset link is invalid or has expired. Request a new link and try again.';

    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            Password::sendResetLink($request->safe()->only(['email']));
        } catch (Throwable $exception) {
            // Delivery failures must not disclose whether this email belongs to an account.
            report($exception);
        }

        return ApiResponse::data([
            'message' => self::LINK_RESPONSE_MESSAGE,
        ], Response::HTTP_ACCEPTED);
    }

    public function update(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->invalidateDatabaseSessions($user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(self::INVALID_TOKEN_MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::data([
            'message' => 'Your password has been reset. You can now sign in with your new password.',
        ]);
    }

    private function invalidateDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table'))
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }
}
