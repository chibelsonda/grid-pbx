<?php

namespace App\Domains\CallRouting\Controllers;

use App\Domains\CallRouting\Contracts\SwitchCallflowEntryPointGateway;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Requests\CreateCallflowNodeRequest;
use App\Domains\CallRouting\Requests\CreateInlineCallflowNodeRequest;
use App\Domains\CallRouting\Requests\DeleteCallflowNodeRequest;
use App\Domains\CallRouting\Requests\ListCallflowsRequest;
use App\Domains\CallRouting\Requests\MoveCallflowNodeRequest;
use App\Domains\CallRouting\Requests\ReorderCallflowNodesRequest;
use App\Domains\CallRouting\Requests\StoreCallflowRequest;
use App\Domains\CallRouting\Requests\UpdateCallflowEntryPointsRequest;
use App\Domains\CallRouting\Requests\UpdateCallflowNodeRequest;
use App\Domains\CallRouting\Requests\UpdateCallflowRequest;
use App\Domains\CallRouting\Requests\UpdateInlineCallflowNodeRequest;
use App\Domains\CallRouting\Resources\CallflowResource;
use App\Domains\CallRouting\Services\CallflowEditorService;
use App\Domains\CallRouting\Services\CallflowMutationService;
use App\Domains\CallRouting\Services\CallflowService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CallflowController extends Controller
{
    public function index(
        ListCallflowsRequest $request,
        string $account,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $validated = $request->validated();
        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'extensions')
            ->first();

        return CallflowResource::collection($callflows->paginate(
            $switchAccount,
            $validated,
            (int) ($validated['per_page'] ?? 25),
        ))->additional(['meta' => ['sync' => [
            'status' => $checkpoint?->status->value ?? 'stale',
            'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
            'error_message' => $checkpoint?->publicErrorMessage(),
            'scope' => 'pbx_projection',
        ]]]);
    }

    public function show(
        Request $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
        SwitchCallflowEntryPointGateway $entryPointGateway,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return new CallflowResource($callflows->find($switchAccount, $callflow));
    }

    public function createOptions(
        Request $request,
        string $account,
        SwitchAccountService $accounts,
        CallflowEditorService $editor,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return ApiResponse::data($editor->editor($switchAccount));
    }

    public function store(
        StoreCallflowRequest $request,
        string $account,
        SwitchAccountService $accounts,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchCallflow::class, $switchAccount]);
        $mutations = app(CallflowMutationService::class);

        return (new CallflowResource($mutations->create(
            $switchAccount,
            $user,
            $request->validated(),
            $request->ip(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function edit(
        Request $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
        CallflowEditorService $editor,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);

        return ApiResponse::data($editor->editor($switchAccount, $switchCallflow));
    }

    public function update(
        UpdateCallflowRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);
        $mutations = app(CallflowMutationService::class);

        return new CallflowResource($mutations->update(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function updateEntryPoints(
        UpdateCallflowEntryPointsRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
        SwitchCallflowEntryPointGateway $entryPointGateway,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->updateEntryPoints(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $entryPointGateway,
            $request->ip(),
        ));
    }

    public function destroy(
        Request $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('delete', [$switchCallflow, $switchAccount]);
        app(CallflowMutationService::class)->delete(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->ip(),
        );

        return ApiResponse::noContent();
    }

    public function moveNode(
        MoveCallflowNodeRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->moveTreeNode(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function createNode(
        CreateCallflowNodeRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->createTreeNode(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function reorderNodes(
        ReorderCallflowNodesRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->reorderTreeNodes(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function updateNode(
        UpdateCallflowNodeRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->updateTreeNode(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function deleteNode(
        DeleteCallflowNodeRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->deleteTreeNode(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function createInlineNode(
        CreateInlineCallflowNodeRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->createInlineTreeNode(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }

    public function updateInlineNode(
        UpdateInlineCallflowNodeRequest $request,
        string $account,
        string $callflow,
        SwitchAccountService $accounts,
        CallflowService $callflows,
    ): CallflowResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $switchCallflow = $callflows->find($switchAccount, $callflow);
        Gate::authorize('update', [$switchCallflow, $switchAccount]);

        return new CallflowResource(app(CallflowMutationService::class)->updateInlineTreeNode(
            $switchAccount,
            $switchCallflow,
            $user,
            $request->validated(),
            $request->ip(),
        ));
    }
}
