<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Resources\BillingInvoiceResource;
use App\Domains\Billing\Services\BillingInvoiceService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BillingInvoiceController extends Controller
{
    public function show(
        Request $request,
        string $account,
        string $invoice,
        SwitchAccountService $accounts,
        BillingInvoiceService $invoices,
    ): BillingInvoiceResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchServiceSummary::class, $switchAccount]);
        $billingInvoice = $invoices->find($switchAccount, $invoice);

        abort_if($billingInvoice === null, 404, 'Invoice is not available.');

        return new BillingInvoiceResource($billingInvoice);
    }

    public function document(
        Request $request,
        string $account,
        string $invoice,
        SwitchAccountService $accounts,
        BillingInvoiceService $invoices,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        Gate::authorize('viewAny', [SwitchServiceSummary::class, $switchAccount]);
        $billingInvoice = $invoices->find($switchAccount, $invoice);

        abort_if($billingInvoice === null, 404, 'Invoice is not available.');

        $response = $invoices->streamDocument(
            $switchAccount,
            $billingInvoice,
            $user,
            $request->ip(),
        );

        abort_if($response === null, 404, 'Invoice document is not available.');

        return $response;
    }
}
