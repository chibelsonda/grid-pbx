<?php

namespace App\Domains\PhoneNumbers\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\PhoneNumbers\Requests\ListPhoneNumbersRequest;
use App\Domains\PhoneNumbers\Resources\PhoneNumberResource;
use App\Domains\PhoneNumbers\Services\PhoneNumberService;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PhoneNumberController extends Controller
{
    public function index(
        ListPhoneNumbersRequest $request,
        string $account,
        SwitchAccountService $accounts,
        PhoneNumberService $phoneNumbers,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $validated = $request->validated();
        $checkpoint = SyncCheckpoint::query()
            ->where('switch_account_id', $switchAccount->getKey())
            ->where('resource_type', 'phone_numbers')
            ->first();

        return PhoneNumberResource::collection($phoneNumbers->paginate(
            $switchAccount,
            $validated,
            (int) ($validated['per_page'] ?? 25),
        ))->additional(['meta' => ['sync' => [
            'status' => $checkpoint?->status->value ?? 'stale',
            'last_successful_at' => $checkpoint?->last_successful_at?->toIso8601String(),
            'error_message' => $checkpoint?->error_message,
        ]]]);
    }

    public function show(
        Request $request,
        string $account,
        string $phoneNumber,
        SwitchAccountService $accounts,
        PhoneNumberService $phoneNumbers,
    ): PhoneNumberResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);

        return new PhoneNumberResource($phoneNumbers->find($switchAccount, $phoneNumber));
    }
}
