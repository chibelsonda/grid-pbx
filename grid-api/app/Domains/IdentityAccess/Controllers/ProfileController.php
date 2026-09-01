<?php

namespace App\Domains\IdentityAccess\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\IdentityAccess\Requests\UpdateProfileRequest;
use App\Domains\IdentityAccess\Resources\SessionResource;
use App\Domains\IdentityAccess\Services\ProfileService;
use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function update(
        UpdateProfileRequest $request,
        ProfileService $profiles,
    ): SessionResource {
        /** @var User $user */
        $user = $request->user();

        return new SessionResource($profiles->updateName(
            $user,
            $request->validated('name'),
            $request->ip(),
        ));
    }
}
