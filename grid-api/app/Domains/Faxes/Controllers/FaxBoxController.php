<?php

namespace App\Domains\Faxes\Controllers;

use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Faxes\Requests\ListFaxBoxesRequest;
use App\Domains\Faxes\Requests\SaveFaxBoxRequest;
use App\Domains\Faxes\Resources\FaxBoxResource;
use App\Domains\Faxes\Services\FaxBoxMutationService;
use App\Domains\Faxes\Services\FaxBoxService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FaxBoxController extends Controller
{
    public function index(ListFaxBoxesRequest $request, string $account, SwitchAccountService $accounts, FaxBoxService $service): AnonymousResourceCollection
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchFaxBox::class, $switchAccount]);
        $data = $request->validated();

        return FaxBoxResource::collection($service->paginate($switchAccount, $data, (int) ($data['per_page'] ?? 25)));
    }

    public function options(Request $request, string $account, SwitchAccountService $accounts, FaxBoxService $service): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchFaxBox::class, $switchAccount]);

        return ApiResponse::data($service->options($switchAccount));
    }

    public function show(Request $request, string $account, string $faxBox, SwitchAccountService $accounts, FaxBoxService $service): FaxBoxResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $box = $service->find($switchAccount, $faxBox);
        Gate::authorize('view', [$box, $switchAccount]);

        return new FaxBoxResource($box);
    }

    public function store(SaveFaxBoxRequest $request, string $account, SwitchAccountService $accounts, FaxBoxMutationService $mutations): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('create', [SwitchFaxBox::class, $switchAccount]);

        return (new FaxBoxResource($mutations->create($switchAccount, $user, $request->validated(), $request->ip())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveFaxBoxRequest $request, string $account, string $faxBox, SwitchAccountService $accounts, FaxBoxService $service, FaxBoxMutationService $mutations): FaxBoxResource
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $box = $service->find($switchAccount, $faxBox);
        Gate::authorize('update', [$box, $switchAccount]);

        return new FaxBoxResource($mutations->update($switchAccount, $box, $user, $request->validated(), $request->ip()));
    }

    public function destroy(Request $request, string $account, string $faxBox, SwitchAccountService $accounts, FaxBoxService $service, FaxBoxMutationService $mutations): Response
    { /** @var User $user */ $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $box = $service->find($switchAccount, $faxBox);
        Gate::authorize('delete', [$box, $switchAccount]);
        $mutations->delete($switchAccount, $box, $user, $request->ip());

        return ApiResponse::noContent();
    }
}
