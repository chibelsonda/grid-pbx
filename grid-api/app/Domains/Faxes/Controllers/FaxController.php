<?php

namespace App\Domains\Faxes\Controllers;

use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Faxes\Requests\ListFaxesRequest;
use App\Domains\Faxes\Resources\FaxResource;
use App\Domains\Faxes\Services\FaxService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FaxController extends Controller
{
    public function index(ListFaxesRequest $request, string $account, SwitchAccountService $accounts, FaxService $service): AnonymousResourceCollection { /** @var User $user */ $user = $request->user(); $switchAccount = $accounts->findAccessible($user, $account); Gate::authorize('viewAny', [SwitchFax::class, $switchAccount]); $data = $request->validated(); return FaxResource::collection($service->paginate($switchAccount, $data, (int) ($data['per_page'] ?? 25))); }
    public function show(Request $request, string $account, string $fax, SwitchAccountService $accounts, FaxService $service): FaxResource { /** @var User $user */ $user = $request->user(); $switchAccount = $accounts->findAccessible($user, $account); $model = $service->find($switchAccount, $fax); Gate::authorize('view', [$model, $switchAccount]); return new FaxResource($model); }
}
