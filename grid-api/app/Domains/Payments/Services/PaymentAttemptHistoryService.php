<?php

namespace App\Domains\Payments\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentAttemptHistoryService
{
    /** @return Collection<int, PaymentAttempt> */
    public function recent(SwitchAccount $account, int $limit = 25): Collection
    {
        return PaymentAttempt::query()
            ->with('sourceAttempt')
            ->where('switch_account_id', $account->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('payment_attempt_id')
            ->limit(max(1, min(50, $limit)))
            ->get();
    }

    public function detail(SwitchAccount $account, string $attemptId): PaymentAttempt
    {
        return PaymentAttempt::query()
            ->with([
                'sourceAttempt',
                'events' => fn (HasMany $query): HasMany => $query
                    ->orderBy('created_at')
                    ->orderBy('payment_attempt_event_id'),
            ])
            ->where('switch_account_id', $account->getKey())
            ->where('id', $attemptId)
            ->firstOrFail();
    }
}
