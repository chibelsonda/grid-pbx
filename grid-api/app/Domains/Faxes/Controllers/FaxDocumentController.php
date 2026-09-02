<?php

namespace App\Domains\Faxes\Controllers;

use App\Domains\Faxes\Services\FaxDocumentService;
use App\Domains\Faxes\Services\FaxService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Http\Controllers\Controller;
use App\Support\Http\Requests\StreamBinaryResourceRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaxDocumentController extends Controller
{
    public function show(
        StreamBinaryResourceRequest $request,
        string $account,
        string $fax,
        SwitchAccountService $accounts,
        FaxService $service,
        FaxDocumentService $documents,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $model = $service->find($switchAccount, $fax);
        Gate::authorize('view', [$model, $switchAccount]);
        abort_unless($model->has_document, 404, 'Fax document is not available.');
        $range = $request->header('Range');

        if ($range !== null && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range) !== 1) {
            abort(416, 'The requested byte range is invalid.');
        }

        return $documents->stream(
            $switchAccount,
            $model,
            $user,
            $range,
            $request->boolean('download'),
            $request->ip(),
        );
    }
}
