<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountSettingsService
{
    public function __construct(
        private readonly SwitchAccountGateway $gateway,
        private readonly AccountProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    public function refresh(SwitchAccount $account, User $actor, ?string $ipAddress = null): SwitchAccount
    {
        $snapshot = $this->gateway->find($account);

        return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchAccount {
            $projected = $this->projection->project($account, $snapshot);
            $this->audit->record(
                $actor,
                $projected,
                'account.settings_refreshed',
                'succeeded',
                $projected->switch_account_id,
                ['projection_version' => $projected->projection_version],
                $ipAddress,
                'account',
            );

            return $projected;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchAccount {
        try {
            $snapshot = $this->gateway->updateSettings($account, $this->resolveCallerIdNumbers($account, $data));

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchAccount {
                $projected = $this->projection->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $projected,
                    'account.settings_updated',
                    'succeeded',
                    $projected->switch_account_id,
                    ['projection_version' => $projected->projection_version],
                    $ipAddress,
                    'account',
                );

                return $projected;
            });
        } catch (Throwable $exception) {
            $this->audit->record(
                $actor,
                $account,
                'account.settings_updated',
                'failed',
                $account->switch_account_id,
                ['error' => $exception->getMessage()],
                $ipAddress,
                'account',
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveCallerIdNumbers(SwitchAccount $account, array $data): array
    {
        foreach (['external' => false, 'emergency' => true] as $scope => $requiresE911) {
            $preserveNumber = data_get($data, "caller_id.{$scope}.preserve_number") === true;
            $publicId = data_get($data, "caller_id.{$scope}.phone_number_id");
            if ($preserveNumber) {
                $data['caller_id'][$scope]['number'] = data_get($account->switch_json, "caller_id.{$scope}.number");
                unset(
                    $data['caller_id'][$scope]['phone_number_id'],
                    $data['caller_id'][$scope]['preserve_number'],
                );

                continue;
            }

            $phoneNumber = is_string($publicId) && $publicId !== ''
                ? $account->phoneNumbers()->where('id', $publicId)->first()
                : null;

            if (is_string($publicId) && $publicId !== '' && $phoneNumber === null) {
                throw ValidationException::withMessages([
                    "caller_id.{$scope}.phone_number_id" => 'Select a phone number assigned to this account.',
                ]);
            }

            if ($requiresE911 && $phoneNumber !== null && ! $phoneNumber->isE911Enabled()) {
                throw ValidationException::withMessages([
                    'caller_id.emergency.phone_number_id' => 'Select a phone number with E911 enabled.',
                ]);
            }

            $data['caller_id'][$scope]['number'] = $phoneNumber?->number;
            unset(
                $data['caller_id'][$scope]['phone_number_id'],
                $data['caller_id'][$scope]['preserve_number'],
            );
        }

        return $data;
    }
}
