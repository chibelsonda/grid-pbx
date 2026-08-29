<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountStatusService
{
    public function __construct(
        private readonly SwitchAccountGateway $gateway,
        private readonly AccountProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    public function update(
        SwitchAccount $account,
        User $actor,
        bool $enabled,
        ?string $ipAddress = null,
    ): SwitchAccount {
        try {
            $snapshot = $this->gateway->updateEnabled($account, $enabled);

            return DB::transaction(function () use ($account, $actor, $enabled, $ipAddress, $snapshot): SwitchAccount {
                $projected = $this->projection->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $projected,
                    $enabled ? 'account.enabled' : 'account.disabled',
                    'succeeded',
                    $projected->switch_account_id,
                    ['enabled' => $enabled, 'projection_version' => $projected->projection_version],
                    $ipAddress,
                    'account',
                );

                return $projected;
            });
        } catch (Throwable $exception) {
            $this->audit->record(
                $actor,
                $account,
                $enabled ? 'account.enabled' : 'account.disabled',
                'failed',
                $account->switch_account_id,
                ['enabled' => $enabled, 'error' => $exception->getMessage()],
                $ipAddress,
                'account',
            );

            throw $exception;
        }
    }
}
