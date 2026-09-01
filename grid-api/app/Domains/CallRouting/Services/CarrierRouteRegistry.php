<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Validation\ValidationException;

/**
 * Resolves public carrier profile UUIDs into private Switch routing settings.
 *
 * Global routing mirrors Monster's offnet action. Account routing mirrors the
 * resources action and may use a projected reseller ancestor. Raw Switch
 * account identifiers never cross the public API boundary.
 */
final class CarrierRouteRegistry
{
    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function validatedProfileSettings(
        CallflowIntegrationType $type,
        array $settings,
    ): array {
        if ($type === CallflowIntegrationType::GlobalCarrier) {
            if ($settings !== []) {
                throw ValidationException::withMessages([
                    'settings' => ['Global Carrier profiles do not accept custom Switch routing fields.'],
                ]);
            }

            return [];
        }

        $scope = $settings['scope'] ?? null;

        if ($type !== CallflowIntegrationType::AccountCarrier
            || ! is_string($scope)
            || ! in_array($scope, ['account', 'reseller'], true)
            || array_keys($settings) !== ['scope']) {
            throw ValidationException::withMessages([
                'settings.scope' => ['Select account resources or a projected reseller ancestor.'],
            ]);
        }

        return ['scope' => $scope];
    }

    /** @return list<array{id: string, label: string, module: string, scope: string}> */
    public function publicRoutes(SwitchAccount $account): array
    {
        return array_values(array_map(
            static fn (array $route): array => [
                'id' => $route['id'],
                'label' => $route['label'],
                'module' => $route['module'],
                'scope' => $route['scope'],
            ],
            $this->routes($account),
        ));
    }

    /** @return array{enabled: bool, reason: ?string} */
    public function capability(SwitchAccount $account, string $module): array
    {
        if (! in_array($module, ['offnet', 'resources'], true)) {
            return ['enabled' => false, 'reason' => 'This carrier action is not supported.'];
        }

        $available = array_filter(
            $this->routes($account),
            static fn (array $route): bool => $route['module'] === $module,
        );

        if ($available === []) {
            $label = $module === 'offnet' ? 'Global Carrier' : 'Account Carrier';

            return [
                'enabled' => false,
                'reason' => "No active administrator-approved {$label} profile is configured for this account.",
            ];
        }

        return ['enabled' => true, 'reason' => null];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function settingsForSwitch(
        SwitchAccount $account,
        string $module,
        array $settings,
    ): array {
        $profile = $this->findRoute($account, $module, $settings['route_profile_id'] ?? null);

        if ($profile === null) {
            throw ValidationException::withMessages([
                'data.route_profile_id' => ['Select an active administrator-approved carrier profile.'],
            ]);
        }

        $switchSettings = [
            'skip_module' => ($settings['skip_module'] ?? false) === true,
        ];

        if ($module === 'resources' && $profile['scope'] === 'reseller') {
            $resellerId = $this->projectedResellerSwitchAccountId($account);

            if ($resellerId === null) {
                throw ValidationException::withMessages([
                    'data.route_profile_id' => ['The account has no projected reseller ancestor available for routing.'],
                ]);
            }

            $switchSettings['hunt_account_id'] = $resellerId;
        }

        return $switchSettings;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function publicSettings(SwitchAccount $account, string $module, array $data): array
    {
        $route = $this->matchSwitchData($account, $module, $data);

        return [
            'supported_configuration' => $route !== null,
            'route_profile_id' => $route['id'] ?? null,
            'route_profile_label' => $route['label'] ?? null,
            'route_scope' => $route['scope'] ?? null,
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function routes(SwitchAccount $account): array
    {
        return $account->callflowIntegrationProfiles()
            ->whereIn('integration_type', [
                CallflowIntegrationType::GlobalCarrier->value,
                CallflowIntegrationType::AccountCarrier->value,
            ])
            ->where('is_active', true)
            ->orderBy('integration_type')
            ->orderBy('name')
            ->orderBy('callflow_integration_profile_id')
            ->get()
            ->map(fn (CallflowIntegrationProfile $profile): ?array => $this->normalize($account, $profile))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function normalize(
        SwitchAccount $account,
        CallflowIntegrationProfile $profile,
    ): ?array {
        $settings = is_array($profile->settings) ? $profile->settings : null;

        if ($settings === null) {
            return null;
        }

        if ($profile->integration_type === CallflowIntegrationType::GlobalCarrier) {
            if ($settings !== []) {
                return null;
            }

            return [
                'id' => $profile->id,
                'label' => trim($profile->name),
                'module' => 'offnet',
                'scope' => 'global',
            ];
        }

        $scope = $settings['scope'] ?? null;

        if (! is_string($scope)
            || ! in_array($scope, ['account', 'reseller'], true)
            || ($scope === 'reseller' && $this->projectedResellerSwitchAccountId($account) === null)) {
            return null;
        }

        return [
            'id' => $profile->id,
            'label' => trim($profile->name),
            'module' => 'resources',
            'scope' => $scope,
        ];
    }

    /** @return array<string, mixed>|null */
    private function findRoute(SwitchAccount $account, string $module, mixed $id): ?array
    {
        if (! is_string($id)) {
            return null;
        }

        return collect($this->routes($account))->first(
            static fn (array $route): bool => $route['module'] === $module && $route['id'] === $id,
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    private function matchSwitchData(SwitchAccount $account, string $module, array $data): ?array
    {
        $routes = array_filter(
            $this->routes($account),
            static fn (array $route): bool => $route['module'] === $module,
        );

        if ($module === 'offnet') {
            return array_values($routes)[0] ?? null;
        }

        $huntAccountId = $data['hunt_account_id'] ?? null;

        if ($huntAccountId === null || $huntAccountId === '') {
            return collect($routes)->first(
                static fn (array $route): bool => $route['scope'] === 'account',
            );
        }

        $resellerId = $this->projectedResellerSwitchAccountId($account);

        if (! is_string($huntAccountId)
            || $resellerId === null
            || ! hash_equals($resellerId, $huntAccountId)) {
            return null;
        }

        return collect($routes)->first(
            static fn (array $route): bool => $route['scope'] === 'reseller',
        );
    }

    private function projectedResellerSwitchAccountId(SwitchAccount $account): ?string
    {
        $candidate = $account->parentAccount;
        $visited = [];

        while ($candidate instanceof SwitchAccount && ! isset($visited[$candidate->getKey()])) {
            $visited[$candidate->getKey()] = true;

            if ($candidate->is_reseller
                && is_string($candidate->switch_account_id)
                && $candidate->switch_account_id !== '') {
                return $candidate->switch_account_id;
            }

            $candidate = $candidate->parentAccount;
        }

        return null;
    }
}
