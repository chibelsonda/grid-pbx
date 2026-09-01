<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use LogicException;

class CallflowIntegrationProfileService
{
    public function __construct(
        private readonly PivotEndpointRegistry $pivotEndpoints,
        private readonly WebhookEndpointRegistry $webhookEndpoints,
        private readonly DisaAccessPolicyRegistry $disaPolicies,
        private readonly CarrierRouteRegistry $carrierRoutes,
        private readonly AuditService $audit,
    ) {}

    /** @return Collection<int, CallflowIntegrationProfile> */
    public function list(SwitchAccount $account): Collection
    {
        return $account->callflowIntegrationProfiles()
            ->orderBy('integration_type')
            ->orderBy('name')
            ->get();
    }

    public function find(SwitchAccount $account, string $profileId): CallflowIntegrationProfile
    {
        $profile = $account->callflowIntegrationProfiles()->where('id', $profileId)->first();

        if ($profile === null) {
            throw (new ModelNotFoundException)->setModel(CallflowIntegrationProfile::class, [$profileId]);
        }

        return $profile;
    }

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): CallflowIntegrationProfile {
        $type = CallflowIntegrationType::from($data['integration_type']);
        $settings = $this->validatedSettings($type, $data['settings']);
        $profile = $account->callflowIntegrationProfiles()->create([
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
            'integration_type' => $type,
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'settings' => $settings,
        ]);

        $this->audit->record(
            $actor,
            $account,
            'callflow.integration_profile_created',
            'succeeded',
            $profile->id,
            $this->auditContext($profile),
            $ipAddress,
            'callflow_integration_profile',
        );

        return $profile;
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        CallflowIntegrationProfile $profile,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): CallflowIntegrationProfile {
        if (array_key_exists('integration_type', $data)) {
            $submittedType = CallflowIntegrationType::from($data['integration_type']);

            if ($submittedType !== $profile->integration_type) {
                throw ValidationException::withMessages([
                    'integration_type' => ['An integration profile type cannot be changed.'],
                ]);
            }

            unset($data['integration_type']);
        }

        if (array_key_exists('settings', $data)) {
            $data['settings'] = $this->validatedSettings($profile->integration_type, $data['settings']);
        }

        $profile->fill([
            ...$data,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            $actor,
            $account,
            'callflow.integration_profile_updated',
            'succeeded',
            $profile->id,
            $this->auditContext($profile),
            $ipAddress,
            'callflow_integration_profile',
        );

        return $profile->refresh();
    }

    public function delete(
        SwitchAccount $account,
        CallflowIntegrationProfile $profile,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        $profile->delete();

        $this->audit->record(
            $actor,
            $account,
            'callflow.integration_profile_deleted',
            'succeeded',
            $profile->id,
            $this->auditContext($profile),
            $ipAddress,
            'callflow_integration_profile',
        );
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function validatedSettings(CallflowIntegrationType $type, array $settings): array
    {
        return match ($type) {
            CallflowIntegrationType::Pivot => $this->pivotEndpoints->validatedProfileSettings($settings),
            CallflowIntegrationType::Webhook => $this->webhookEndpoints->validatedProfileSettings($settings),
            CallflowIntegrationType::Disa => $this->disaPolicies->validatedProfileSettings($settings),
            CallflowIntegrationType::GlobalCarrier,
            CallflowIntegrationType::AccountCarrier => $this->carrierRoutes->validatedProfileSettings($type, $settings),
            default => throw new LogicException('The integration profile type has no settings validator.'),
        };
    }

    /** @return array{profile_id: string, integration_type: string, name: string, is_active: bool} */
    private function auditContext(CallflowIntegrationProfile $profile): array
    {
        return [
            'profile_id' => $profile->id,
            'integration_type' => $profile->integration_type->value,
            'name' => $profile->name,
            'is_active' => $profile->is_active,
        ];
    }
}
