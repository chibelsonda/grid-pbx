<?php

namespace App\Domains\Queues\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Queues\Requests\ListQueuesRequest;
use App\Domains\Queues\Requests\SaveQueueRequest;
use App\Domains\Queues\Resources\QueueResource;
use App\Domains\Queues\Services\QueueMutationService;
use App\Domains\Queues\Services\QueueService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QueueController extends Controller
{
    public function index(ListQueuesRequest $request, string $account, SwitchAccountService $accounts, QueueService $queues): AnonymousResourceCollection
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchQueue::class, $switchAccount]);
        $validated = $request->validated();

        return QueueResource::collection($queues->paginate($switchAccount, $validated, (int) ($validated['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, QueueService $queues): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchQueue::class, $switchAccount]);

        return response()->json(['data' => $queues->options($switchAccount)]);
    }

    public function show(Request $request, string $account, string $queue, SwitchAccountService $accounts, QueueService $queues): QueueResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $queues->find($switchAccount, $queue);
        Gate::authorize('view', [$model, $switchAccount]);

        return new QueueResource($model);
    }

    public function store(SaveQueueRequest $request, string $account, SwitchAccountService $accounts, QueueMutationService $mutations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchQueue::class, $switchAccount]);

        return (new QueueResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveQueueRequest $request, string $account, string $queue, SwitchAccountService $accounts, QueueService $queues, QueueMutationService $mutations): QueueResource
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $queues->find($switchAccount, $queue);
        Gate::authorize('update', [$model, $switchAccount]);

        return new QueueResource($mutations->update($switchAccount, $model, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $queue, SwitchAccountService $accounts, QueueService $queues, QueueMutationService $mutations): Response
    {
        /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $queues->find($switchAccount, $queue);
        Gate::authorize('delete', [$model, $switchAccount]);
        $mutations->delete($switchAccount, $model, $user, $request->ip());

        return response()->noContent();
    }
}
