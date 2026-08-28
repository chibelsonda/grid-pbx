<?php

namespace App\Domains\Directories\Controllers;

use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Directories\Requests\ListDirectoriesRequest;
use App\Domains\Directories\Requests\SaveDirectoryRequest;
use App\Domains\Directories\Resources\DirectoryResource;
use App\Domains\Directories\Services\DirectoryMutationService;
use App\Domains\Directories\Services\DirectoryService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DirectoryController extends Controller
{
    public function index(ListDirectoriesRequest $request, string $account, SwitchAccountService $accounts, DirectoryService $directories): AnonymousResourceCollection
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchDirectory::class, $switchAccount]);
        $validated = $request->validated();

        return DirectoryResource::collection($directories->paginate($switchAccount, $validated, (int) ($validated['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, DirectoryService $directories): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchDirectory::class, $switchAccount]);

        return response()->json(['data' => $directories->options($switchAccount)]);
    }

    public function show(Request $request, string $account, string $directory, SwitchAccountService $accounts, DirectoryService $directories): DirectoryResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $directories->find($switchAccount, $directory);
        Gate::authorize('view', [$model, $switchAccount]);

        return new DirectoryResource($model);
    }

    public function store(SaveDirectoryRequest $request, string $account, SwitchAccountService $accounts, DirectoryMutationService $mutations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchDirectory::class, $switchAccount]);

        return (new DirectoryResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveDirectoryRequest $request, string $account, string $directory, SwitchAccountService $accounts, DirectoryService $directories, DirectoryMutationService $mutations): DirectoryResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $directories->find($switchAccount, $directory);
        Gate::authorize('update', [$model, $switchAccount]);

        return new DirectoryResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $directory, SwitchAccountService $accounts, DirectoryService $directories, DirectoryMutationService $mutations): Response
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $directories->find($switchAccount, $directory);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return response()->noContent();
    }
}
