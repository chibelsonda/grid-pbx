<?php

namespace App\Domains\IdentityAccess\Controllers;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\IdentityAccess\Requests\LoginRequest;
use App\Domains\IdentityAccess\Resources\SessionResource;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function show(Request $request): SessionResource
    {
        return new SessionResource($request->user());
    }

    public function store(LoginRequest $request, AuditService $audit): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $audit->record(
                null,
                null,
                'auth.login',
                'failed',
                $this->emailHash((string) $credentials['email']),
                [],
                $request->ip(),
                'session',
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = $request->user();
        $audit->record(
            $user,
            null,
            'auth.login',
            'succeeded',
            null,
            [],
            $request->ip(),
            'session',
        );

        return (new SessionResource($user))->response();
    }

    public function destroy(Request $request, AuditService $audit): Response
    {
        /** @var User $user */
        $user = $request->user();
        $audit->record(
            $user,
            null,
            'auth.logout',
            'succeeded',
            null,
            [],
            $request->ip(),
            'session',
        );
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::noContent();
    }

    private function emailHash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }
}
