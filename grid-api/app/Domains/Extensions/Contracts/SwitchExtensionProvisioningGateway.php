<?php

namespace App\Domains\Extensions\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchExtensionProvisioningGateway
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createUser(SwitchAccount $account, array $data): array;

    public function deleteUser(SwitchAccount $account, string $resourceId): void;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateUser(SwitchAccount $account, string $resourceId, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVoicemailBox(SwitchAccount $account, array $data): array;

    public function deleteVoicemailBox(SwitchAccount $account, string $resourceId): void;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateVoicemailBox(SwitchAccount $account, string $resourceId, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createDevice(SwitchAccount $account, array $data): array;

    public function deleteDevice(SwitchAccount $account, string $resourceId): void;

    /** @return array<string, mixed> */
    public function createManagedCallflow(
        SwitchAccount $account,
        string $name,
        string $extension,
        string $userResourceId,
        ?string $voicemailBoxResourceId,
    ): array;

    public function deleteCallflow(SwitchAccount $account, string $resourceId): void;

    /** @return array<string, mixed> */
    public function updateManagedCallflow(
        SwitchAccount $account,
        string $resourceId,
        string $userResourceId,
        string $previousExtension,
        string $extension,
        string $name,
        ?string $voicemailBoxResourceId,
    ): array;
}
