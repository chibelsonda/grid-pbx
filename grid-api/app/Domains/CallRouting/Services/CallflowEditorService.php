<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Organizations\Models\SwitchAccount;

class CallflowEditorService
{
    /** @return array<string, mixed> */
    public function editor(SwitchAccount $account, ?SwitchCallflow $callflow = null): array
    {
        return [
            'mode' => $callflow === null ? 'create' : 'update',
            'editable' => $callflow === null || (! $callflow->is_feature_code && $callflow->flow_structure !== null),
            'blocked_reason' => match (true) {
                $callflow?->is_feature_code === true => 'Feature-code routes are read-only in the guided editor.',
                $callflow !== null && $callflow->flow_structure === null => 'This route has no root flow node to edit.',
                default => null,
            },
            'destination_types' => [
                ['value' => 'extension', 'label' => 'Extension'],
                ['value' => 'device', 'label' => 'Device'],
                ['value' => 'voicemail', 'label' => 'Voicemail'],
                ['value' => 'callflow', 'label' => 'Another call route'],
                ['value' => 'media', 'label' => 'Media'],
                ['value' => 'directory', 'label' => 'Directory'],
                ['value' => 'group', 'label' => 'Group / Ring Group'],
                ['value' => 'queue', 'label' => 'Call Queue'],
                ['value' => 'menu', 'label' => 'Menu / IVR'],
            ],
            'destinations' => [
                'extension' => $account->extensions()->orderBy('display_name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->display_name ?? $item->extension ?? 'Unnamed extension',
                    'detail' => $item->extension,
                ])->values()->all(),
                'device' => $account->devices()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name ?? 'Unnamed device',
                    'detail' => $item->device_type,
                ])->values()->all(),
                'voicemail' => $account->voicemailBoxes()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name ?? $item->mailbox ?? 'Unnamed mailbox',
                    'detail' => $item->mailbox,
                ])->values()->all(),
                'callflow' => $account->callflows()
                    ->when($callflow !== null, fn ($query) => $query->whereKeyNot($callflow->getKey()))
                    ->orderBy('name')->get()->map(fn ($item): array => [
                        'id' => $item->id,
                        'label' => $item->name ?? ($item->numbers[0] ?? 'Unnamed route'),
                        'detail' => $item->root_module,
                    ])->values()->all(),
                'media' => $account->media()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name ?? 'Voicemail greeting',
                    'detail' => $item->content_type,
                ])->values()->all(),
                'directory' => $account->directories()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => 'Dial-by-name directory',
                ])->values()->all(),
                'group' => $account->groups()->withCount('members')->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => $item->members_count.' members',
                ])->values()->all(),
                'queue' => $account->queues()->withCount('agents')->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => $item->agents_count.' agents',
                ])->values()->all(),
                'menu' => $account->menus()->orderBy('name')->get()->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'detail' => 'Interactive voice menu',
                ])->values()->all(),
            ],
            'phone_numbers' => $account->phoneNumbers()
                ->with('assignedCallflow:callflow_id,id,name')
                ->orderBy('number')
                ->get()
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'number' => $item->number,
                    'state' => $item->state,
                    'selected' => $callflow !== null && $item->assigned_callflow_id === $callflow->getKey(),
                    'available' => $item->assigned_callflow_id === null
                        || ($callflow !== null && $item->assigned_callflow_id === $callflow->getKey()),
                    'assigned_callflow' => $item->assignedCallflow === null ? null : [
                        'id' => $item->assignedCallflow->id,
                        'name' => $item->assignedCallflow->name,
                    ],
                ])->values()->all(),
        ];
    }
}
