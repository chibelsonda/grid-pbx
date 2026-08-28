<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Collection;

class ExtensionRecoveryQueryService
{
    /** @return Collection<int, ExtensionLifecycleOperation> */
    public function pending(SwitchAccount $account): Collection
    {
        return ExtensionLifecycleOperation::query()
            ->where('switch_account_id', $account->getKey())
            ->where(function ($query): void {
                $query->where('status', 'failed')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'running')
                            ->where('updated_at', '<=', now()->subMinutes(15));
                    });
            })
            ->with('extension')
            ->latest('updated_at')
            ->get();
    }

    public function find(SwitchAccount $account, string $operationId): ExtensionLifecycleOperation
    {
        return ExtensionLifecycleOperation::query()
            ->where('switch_account_id', $account->getKey())
            ->where('id', $operationId)
            ->with('extension')
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function summary(ExtensionLifecycleOperation $operation): array
    {
        $context = $operation->context ?? [];
        $completedSteps = $this->stepTypes($operation->completed_steps ?? []);
        $extension = $operation->extension;

        return [
            'id' => $operation->id,
            'operation' => $operation->operation,
            'status' => $operation->status,
            'display_name' => $extension?->display_name
                ?? (is_string($context['display_name'] ?? null) ? $context['display_name'] : 'Extension workflow'),
            'extension' => $extension?->extension
                ?? (is_string($context['extension'] ?? null) ? $context['extension'] : null),
            'extension_id' => $extension?->id,
            'completed_steps' => $completedSteps,
            'failed_step' => is_string($operation->failed_step)
                ? strtok($operation->failed_step, ':')
                : null,
            'recovery_action' => $this->recoveryAction($operation),
            'repair_required' => in_array($operation->status, ['failed', 'running'], true),
            'updated_at' => $operation->updated_at?->toIso8601String(),
        ];
    }

    public function recoveryAction(ExtensionLifecycleOperation $operation): string
    {
        return match ($operation->operation) {
            'provision' => 'cleanup',
            'update' => 'reconcile',
            'delete' => 'resume',
            default => 'unsupported',
        };
    }

    /** @param array<int, mixed> $steps
     * @return list<string>
     */
    private function stepTypes(array $steps): array
    {
        return collect($steps)
            ->filter(fn (mixed $step): bool => is_string($step))
            ->map(fn (string $step): string => strtok($step, ':'))
            ->unique()
            ->values()
            ->all();
    }
}
