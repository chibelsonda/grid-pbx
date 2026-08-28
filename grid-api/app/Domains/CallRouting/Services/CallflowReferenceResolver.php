<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;

class CallflowReferenceResolver
{
    /**
     * @param  array<string, mixed>|null  $flow
     * @return array<string, mixed>|null
     */
    public function resolve(SwitchAccount $account, ?array $flow): ?array
    {
        if ($flow === null || ! is_string($flow['module'] ?? null)) {
            return null;
        }

        return $this->resolveNode($flow, $this->targetMaps($account));
    }

    public function refresh(SwitchAccount $account): void
    {
        $targets = $this->targetMaps($account);

        foreach ($account->callflows()->get() as $callflow) {
            $flow = $callflow->switch_json['flow'] ?? null;
            $callflow->forceFill([
                'flow_structure' => is_array($flow) ? $this->resolveNode($flow, $targets) : null,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, array{id: string, label: string}>>  $targets
     * @return array<string, mixed>
     */
    private function resolveNode(array $node, array $targets): array
    {
        $module = is_string($node['module'] ?? null) ? $node['module'] : 'unknown';
        $targetType = $this->targetType($module);
        $resourceId = is_array($node['data'] ?? null) && is_string($node['data']['id'] ?? null)
            ? $node['data']['id']
            : null;
        $target = $targetType !== null && $resourceId !== null
            ? ($targets[$targetType][$resourceId] ?? null)
            : null;
        $children = [];

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $branch => $child) {
            if (is_string($branch) && is_array($child)) {
                $children[$branch] = $this->resolveNode($child, $targets);
            }
        }

        return [
            'module' => $module,
            'target' => $targetType === null || $target === null ? null : [
                'type' => $targetType,
                'id' => $target['id'],
                'label' => $target['label'],
            ],
            'reference_status' => match (true) {
                $targetType === null => 'not_applicable',
                $target !== null => 'resolved',
                default => 'unresolved',
            },
            'children' => $children,
        ];
    }

    /** @return array<string, array<string, array{id: string, label: string}>> */
    private function targetMaps(SwitchAccount $account): array
    {
        return [
            'extension' => $account->extensions()->get()->mapWithKeys(fn ($extension): array => [
                $extension->switch_resource_id => [
                    'id' => $extension->id,
                    'label' => $extension->display_name ?? $extension->extension ?? 'Unnamed extension',
                ],
            ])->all(),
            'device' => $account->devices()->get()->mapWithKeys(fn ($device): array => [
                $device->switch_resource_id => [
                    'id' => $device->id,
                    'label' => $device->name ?? 'Unnamed device',
                ],
            ])->all(),
            'voicemail' => $account->voicemailBoxes()->get()->mapWithKeys(fn ($box): array => [
                $box->switch_resource_id => [
                    'id' => $box->id,
                    'label' => $box->name ?? $box->mailbox ?? 'Unnamed mailbox',
                ],
            ])->all(),
            'callflow' => $account->callflows()->get()->mapWithKeys(fn ($callflow): array => [
                $callflow->switch_resource_id => [
                    'id' => $callflow->id,
                    'label' => $callflow->name ?? ($callflow->numbers[0] ?? 'Unnamed route'),
                ],
            ])->all(),
            'media' => $account->media()->get()->mapWithKeys(fn ($media): array => [
                $media->switch_resource_id => [
                    'id' => $media->id,
                    'label' => $media->name ?? 'Unnamed media',
                ],
            ])->all(),
            'directory' => $account->directories()->get()->mapWithKeys(fn ($directory): array => [
                $directory->switch_resource_id => [
                    'id' => $directory->id,
                    'label' => $directory->name,
                ],
            ])->all(),
            'group' => $account->groups()->get()->mapWithKeys(fn ($group): array => [
                $group->switch_resource_id => [
                    'id' => $group->id,
                    'label' => $group->name,
                ],
            ])->all(),
            'queue' => $account->queues()->get()->mapWithKeys(fn ($queue): array => [
                $queue->switch_resource_id => [
                    'id' => $queue->id,
                    'label' => $queue->name,
                ],
            ])->all(),
            'menu' => $account->menus()->get()->mapWithKeys(fn ($menu): array => [
                $menu->switch_resource_id => ['id' => $menu->id, 'label' => $menu->name],
            ])->all(),
        ];
    }

    private function targetType(string $module): ?string
    {
        return match ($module) {
            'user' => 'extension',
            'device' => 'device',
            'voicemail' => 'voicemail',
            'callflow' => 'callflow',
            'play' => 'media',
            'directory' => 'directory',
            'group' => 'group',
            'acdc_member', 'acdc_queue' => 'queue',
            'menu' => 'menu',
            default => null,
        };
    }
}
