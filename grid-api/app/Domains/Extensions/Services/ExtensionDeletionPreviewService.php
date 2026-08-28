<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;

class ExtensionDeletionPreviewService
{
    /** @return array<string, mixed> */
    public function preview(SwitchAccount $account, SwitchExtension $extension): array
    {
        $extension->loadMissing(['devices', 'voicemailBoxes', 'callflows']);
        $managedDevices = $extension->devices->where('is_managed', true)->values();
        $sharedDevices = $extension->devices->where('is_managed', false)->values();
        $managedVoicemail = $extension->voicemailBoxes->where('is_managed', true)->values();
        $sharedVoicemail = $extension->voicemailBoxes->where('is_managed', false)->values();
        $managedCallflows = $extension->callflows->where('is_managed', true)->values();
        $independentCallflows = $extension->callflows->where('is_managed', false)->values();
        $targetIds = array_values(array_filter([
            $extension->id,
            ...$extension->devices->pluck('id')->all(),
            ...$extension->voicemailBoxes->pluck('id')->all(),
            ...$extension->callflows->pluck('id')->all(),
        ]));
        $references = [];
        $unresolvedRoutes = [];

        foreach ($account->callflows()->whereNotIn('callflow_id', $extension->callflows->modelKeys())->get() as $callflow) {
            if ($this->containsTarget($callflow->flow_structure, $targetIds)) {
                $references[] = [
                    'id' => $callflow->id,
                    'name' => $callflow->name ?? ($callflow->numbers[0] ?? 'Unnamed route'),
                ];
            } elseif ($this->containsUnresolvedReference($callflow->flow_structure)) {
                $unresolvedRoutes[] = [
                    'id' => $callflow->id,
                    'name' => $callflow->name ?? ($callflow->numbers[0] ?? 'Unnamed route'),
                ];
            }
        }

        $blockers = [];

        if (! $extension->is_managed || $extension->managed_by_workflow !== 'extension_provisioning') {
            $blockers[] = $this->blocker('not_managed', 'This extension is not owned by the managed GridPBX workflow.');
        }

        if ($managedCallflows->count() !== 1) {
            $blockers[] = $this->blocker('managed_callflow_count', 'Exactly one managed extension callflow is required for safe deletion.');
        }

        foreach ($managedCallflows as $callflow) {
            if ($callflow->phoneNumbers()->exists()) {
                $blockers[] = $this->blocker('assigned_phone_numbers', 'Remove phone-number assignments from the managed callflow first.');
            }
        }

        $messageCount = $managedVoicemail->sum(fn ($box): int => $box->messages()->count());

        if ($messageCount > 0) {
            $blockers[] = $this->blocker('voicemail_not_empty', 'Move or remove all voicemail messages before deleting this extension.');
        }

        if ($sharedDevices->isNotEmpty()) {
            $blockers[] = $this->blocker('shared_devices', 'Detach independently managed devices before deleting this extension.');
        }

        if ($sharedVoicemail->isNotEmpty()) {
            $blockers[] = $this->blocker('shared_voicemail', 'Detach independently managed voicemail boxes before deleting this extension.');
        }

        if ($independentCallflows->isNotEmpty()) {
            $blockers[] = $this->blocker('independent_callflows', 'Detach independently managed callflows before deleting this extension.');
        }

        if ($references !== []) {
            $blockers[] = $this->blocker('referenced_by_callflow', 'Other callflows reference this extension or one of its related resources.');
        }

        if ($unresolvedRoutes !== []) {
            $blockers[] = $this->blocker('unresolved_callflows', 'Resolve unknown callflow dependencies before deleting this extension.');
        }

        $recoveryOperation = ExtensionLifecycleOperation::query()
            ->where('switch_account_id', $account->getKey())
            ->where('switch_extension_id', $extension->getKey())
            ->where('operation', 'delete')
            ->where('status', 'failed')
            ->latest('created_at')
            ->first();
        $completedSteps = collect($recoveryOperation?->completed_steps ?? [])
            ->filter(fn (mixed $step): bool => is_string($step))
            ->values();
        $completedStepTypes = $completedSteps
            ->map(fn (string $step): string => strtok($step, ':'))
            ->unique()
            ->values()
            ->all();

        if ($recoveryOperation !== null
            && $managedCallflows->isEmpty()
            && in_array('callflow', $completedStepTypes, true)) {
            $blockers = array_values(array_filter(
                $blockers,
                fn (array $blocker): bool => $blocker['code'] !== 'managed_callflow_count',
            ));
        }

        return [
            'extension' => [
                'id' => $extension->id,
                'display_name' => $extension->display_name,
                'extension' => $extension->extension,
                'managed' => $extension->is_managed,
            ],
            'can_delete' => $blockers === [],
            'blockers' => $blockers,
            'managed_resources' => [
                'devices' => $managedDevices->map(fn ($device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                ])->all(),
                'voicemail_boxes' => $managedVoicemail->map(fn ($box): array => [
                    'id' => $box->id,
                    'name' => $box->name,
                    'mailbox' => $box->mailbox,
                    'message_count' => $box->messages()->count(),
                ])->all(),
                'callflows' => $managedCallflows->map(fn ($callflow): array => [
                    'id' => $callflow->id,
                    'name' => $callflow->name,
                    'numbers' => $callflow->numbers,
                    'phone_number_count' => $callflow->phoneNumbers()->count(),
                ])->all(),
            ],
            'shared_resources' => [
                'device_count' => $sharedDevices->count(),
                'voicemail_box_count' => $sharedVoicemail->count(),
                'callflow_count' => $independentCallflows->count(),
            ],
            'referencing_callflows' => $references,
            'unresolved_callflows' => $unresolvedRoutes,
            'recovery' => $recoveryOperation === null ? null : [
                'id' => $recoveryOperation->id,
                'completed_steps' => $completedStepTypes,
                'failed_step' => is_string($recoveryOperation->failed_step)
                    ? strtok($recoveryOperation->failed_step, ':')
                    : null,
                'repair_required' => $completedSteps->isNotEmpty(),
            ],
        ];
    }

    /** @param array<string, mixed>|null $node
     * @param  list<string>  $targetIds
     */
    private function containsTarget(?array $node, array $targetIds): bool
    {
        if ($node === null) {
            return false;
        }

        $targetId = is_array($node['target'] ?? null) ? ($node['target']['id'] ?? null) : null;

        if (is_string($targetId) && in_array($targetId, $targetIds, true)) {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child) && $this->containsTarget($child, $targetIds)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed>|null $node */
    private function containsUnresolvedReference(?array $node): bool
    {
        if ($node === null) {
            return false;
        }

        if (($node['reference_status'] ?? null) === 'unresolved') {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child) && $this->containsUnresolvedReference($child)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{code: string, message: string} */
    private function blocker(string $code, string $message): array
    {
        return compact('code', 'message');
    }
}
