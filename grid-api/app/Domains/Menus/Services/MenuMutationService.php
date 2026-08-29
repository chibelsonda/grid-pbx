<?php

namespace App\Domains\Menus\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Menus\Contracts\SwitchMenuGateway;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MenuMutationService
{
    public function __construct(private readonly SwitchMenuGateway $gateway, private readonly MenuProjectionService $projection, private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ipAddress = null): SwitchMenu
    {
        $resourceId = null;

        try {
            $snapshot = $this->gateway->create($account, $this->resolve($account, $data, null));
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;

            if ($resourceId === null) {
                throw new \UnexpectedValueException('Switch menu create response is missing its identifier.');
            }

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchMenu {
                $menu = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'menu.created', 'succeeded', $menu->switch_resource_id, [], $ipAddress, 'menu');

                return $menu;
            });
        } catch (Throwable $exception) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchMenu $menu, User $actor, array $data, ?string $ipAddress = null): SwitchMenu
    {
        $previous = $this->writeDataFromModel($menu);

        try {
            $snapshot = $this->gateway->update($account, $menu->switch_resource_id, $this->resolve($account, $data, $menu));

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchMenu {
                $updated = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'menu.updated', 'succeeded', $updated->switch_resource_id, [], $ipAddress, 'menu');

                return $updated;
            });
        } catch (Throwable $exception) {
            try {
                $this->gateway->update($account, $menu->switch_resource_id, $previous);
            } catch (Throwable) {
            }
            throw $exception;
        }
    }

    public function delete(SwitchAccount $account, SwitchMenu $menu, User $actor, ?string $ipAddress = null): void
    {
        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsMenu($callflow->switch_json['flow'] ?? null, $menu->switch_resource_id)) {
                throw ValidationException::withMessages(['menu' => ['Remove this menu from call routing before deleting it.']]);
            }
        }

        $this->gateway->delete($account, $menu->switch_resource_id);
        DB::transaction(function () use ($account, $actor, $menu, $ipAddress): void {
            $menu->delete();
            $this->audit->record($actor, $account, 'menu.deleted', 'succeeded', $menu->switch_resource_id, [], $ipAddress, 'menu');
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolve(SwitchAccount $account, array $data, ?SwitchMenu $menu): array
    {
        $resolved = [
            ...$data,
            'switch_flags' => $menu === null ? [] : $this->stringList($menu->switch_json['flags'] ?? null),
        ];

        foreach (['greeting', 'invalid', 'transfer', 'exit'] as $type) {
            $publicId = $data["{$type}_media_id"] ?? null;
            $media = empty($publicId) ? null : $account->media()->where('id', $publicId)->first();

            if (! empty($publicId) && $media === null) {
                throw ValidationException::withMessages(["{$type}_media_id" => ['The selected media is unavailable for this account.']]);
            }

            if ($type === 'greeting') {
                $resolved['switch_greeting_media_reference'] = $media?->switch_resource_id;
            } else {
                $resolved["switch_{$type}_media"] = $media?->switch_resource_id ?? (bool) $data["{$type}_media_enabled"];
            }
        }

        return $resolved;
    }

    /** @return array<string, mixed> */
    private function writeDataFromModel(SwitchMenu $menu): array
    {
        return [
            'name' => $menu->name, 'timeout' => $menu->timeout, 'interdigit_timeout' => $menu->interdigit_timeout,
            'max_extension_length' => $menu->max_extension_length, 'retries' => $menu->retries, 'hunt' => $menu->hunt,
            'allow_record_from_offnet' => $menu->allow_record_from_offnet, 'suppress_media' => $menu->suppress_media,
            'record_pin' => null, 'hunt_allow' => $menu->hunt_allow, 'hunt_deny' => $menu->hunt_deny,
            'switch_greeting_media_reference' => $menu->greeting_media_reference,
            'switch_invalid_media' => $menu->invalid_media_reference ?? $menu->invalid_media_enabled,
            'switch_transfer_media' => $menu->transfer_media_reference ?? $menu->transfer_media_enabled,
            'switch_exit_media' => $menu->exit_media_reference ?? $menu->exit_media_enabled,
            'switch_flags' => $this->stringList($menu->switch_json['flags'] ?? null),
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    private function containsMenu(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) {
            return false;
        }
        if (($node['module'] ?? null) === 'menu' && ($node['data']['id'] ?? null) === $resourceId) {
            return true;
        }
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsMenu($child, $resourceId)) {
                return true;
            }
        }

        return false;
    }
}
