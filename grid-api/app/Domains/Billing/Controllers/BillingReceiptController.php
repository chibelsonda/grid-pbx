<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Resources\BillingReceiptResource;
use App\Domains\Billing\Services\BillingReceiptService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BillingReceiptController extends Controller
{
    public function show(
        Request $request,
        string $account,
        string $receipt,
        SwitchAccountService $accounts,
        BillingReceiptService $receipts,
    ): BillingReceiptResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchServiceSummary::class, $switchAccount]);
        $billingReceipt = $receipts->find($switchAccount, $receipt);

        abort_if($billingReceipt === null, 404, 'Receipt is not available.');

        return new BillingReceiptResource($billingReceipt);
    }

    public function document(
        Request $request,
        string $account,
        string $receipt,
        SwitchAccountService $accounts,
        BillingReceiptService $receipts,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchServiceSummary::class, $switchAccount]);
        $billingReceipt = $receipts->find($switchAccount, $receipt);

        abort_if($billingReceipt === null, 404, 'Receipt is not available.');

        $response = $receipts->streamDocument(
            $switchAccount,
            $billingReceipt,
            $user,
            $request->ip(),
        );

        abort_if($response === null, 404, 'Receipt document is not available.');

        return $response;
    }
}
