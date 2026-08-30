<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CallflowMutationService
{
    public function __construct(
        private readonly SwitchCallflowGateway $gateway,
        private readonly CallflowReferenceResolver $references,
        private readonly CallflowEditorService $editor,
        private readonly CallflowTreeMoveValidator $treeMoveValidator,
        private readonly CallflowTreeReorderValidator $treeReorderValidator,
        private readonly CallflowTreeNodeWriteValidator $treeNodeWriteValidator,
        private readonly CallflowInlineNodeDataValidator $inlineNodeDataValidator,
        private readonly CallflowJsonNormalizer $jsonNormalizer,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        [$module, $resourceId, $temporalRuleIds] = $this->destination($account, null, $data);
        [$fallbackModule, $fallbackResourceId] = $this->optionalDestination(
            $account,
            null,
            $data['fallback_destination_type'] ?? null,
            $data['fallback_destination_id'] ?? null,
        );
        $menuBranchOperations = $this->menuBranchOperations($account, null, $module, $data);
        $temporalBranchOperations = $this->temporalBranchOperations($account, null, $module, $data);
        $directTemporalBranchOperations = $this->directTemporalBranchOperations(
            $account,
            null,
            $data,
        );
        [$assignedPhoneNumbers] = $this->phoneNumberSelection(
            $account,
            null,
            $data['phone_number_ids'],
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->create(
                $account,
                $data['name'],
                $module,
                $resourceId,
                $assignedPhoneNumbers,
                $fallbackModule,
                $fallbackResourceId,
                [
                    ...$menuBranchOperations,
                    ...$temporalBranchOperations,
                    ...$directTemporalBranchOperations,
                ],
                $temporalRuleIds,
            ));

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $callflow = $this->project($account, null, $snapshot);
                $this->reconcilePhoneNumbers($account, $callflow, $data['phone_number_ids']);
                $this->recordSuccess($actor, $account, $callflow, 'callflow.created', $data, $ipAddress);

                return $this->load($callflow);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, null, 'callflow.create_failed', $data, $exception, $ipAddress);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        $replaceFallback = (bool) ($data['manage_fallback'] ?? false);

        if ($replaceFallback) {
            $this->editor->assertFallbackEditable($callflow);
        }

        [$module, $resourceId, $temporalRuleIds] = $this->destination($account, $callflow, $data);
        [$fallbackModule, $fallbackResourceId] = $this->optionalDestination(
            $account,
            $callflow,
            $data['fallback_destination_type'] ?? null,
            $data['fallback_destination_id'] ?? null,
        );
        $menuBranchOperations = $this->menuBranchOperations($account, $callflow, $module, $data);
        $temporalBranchOperations = $this->temporalBranchOperations($account, $callflow, $module, $data);
        $directTemporalBranchOperations = $this->directTemporalBranchOperations(
            $account,
            $callflow,
            $data,
        );
        [$assignedPhoneNumbers, $knownPhoneNumbers] = $this->phoneNumberSelection(
            $account,
            $callflow,
            $data['phone_number_ids'],
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->updateDestination(
                $account,
                $callflow->switch_resource_id,
                $module,
                $resourceId,
                $data['name'],
                $assignedPhoneNumbers,
                $knownPhoneNumbers,
                $replaceFallback,
                $fallbackModule,
                $fallbackResourceId,
                [
                    ...$menuBranchOperations,
                    ...$temporalBranchOperations,
                    ...$directTemporalBranchOperations,
                ],
                $temporalRuleIds,
            ));

            return DB::transaction(function () use ($account, $callflow, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->reconcilePhoneNumbers($account, $projected, $data['phone_number_ids']);
                $this->recordSuccess($actor, $account, $projected, 'callflow.updated', $data, $ipAddress);

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, $callflow, 'callflow.update_failed', $data, $exception, $ipAddress);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function moveTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        $this->treeMoveValidator->assertAllowed(
            $callflow,
            $data['source_path'],
            $data['destination_parent_path'],
            $data['destination_branch'],
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->moveTreeNode(
                $account,
                $callflow->switch_resource_id,
                $data['source_path'],
                $data['destination_parent_path'],
                $data['destination_branch'],
            ));

            return DB::transaction(function () use ($account, $callflow, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'callflow.node_moved',
                    'succeeded',
                    $projected->switch_resource_id,
                    [
                        'callflow_id' => $projected->id,
                        'source_path' => $data['source_path'],
                        'destination_parent_path' => $data['destination_parent_path'],
                        'destination_branch' => $data['destination_branch'],
                    ],
                    $ipAddress,
                    'callflow',
                );

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                $callflow,
                'callflow.node_move_failed',
                $data,
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function createTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        [$module, $resourceId] = $this->resolveDestination(
            $account,
            $callflow,
            $data['destination_type'],
            $data['destination_id'],
        );
        $this->editor->assertEditable($callflow);
        $this->treeNodeWriteValidator->assertCanCreate(
            $callflow,
            $data['parent_path'],
            $data['branch'],
            $module,
        );

        return $this->writeTreeNode(
            $account,
            $callflow,
            $actor,
            'create',
            $data['parent_path'],
            $data['branch'],
            $module,
            $resourceId,
            $data,
            $ipAddress,
        );
    }

    /** @param array<string, mixed> $data */
    public function reorderTreeNodes(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        $this->treeReorderValidator->assertAllowed(
            $callflow,
            $data['mode'],
            $data['source_path'],
            $data['target_path'],
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->reorderTreeNodes(
                $account,
                $callflow->switch_resource_id,
                $data['mode'],
                $data['source_path'],
                $data['target_path'],
            ));

            return DB::transaction(function () use ($account, $callflow, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'callflow.nodes_reordered',
                    'succeeded',
                    $projected->switch_resource_id,
                    [
                        'callflow_id' => $projected->id,
                        'mode' => $data['mode'],
                        'source_path' => $data['source_path'],
                        'target_path' => $data['target_path'],
                    ],
                    $ipAddress,
                    'callflow',
                );

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                $callflow,
                'callflow.node_reorder_failed',
                $data,
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function createInlineTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        $settings = $this->inlineSettingsForSwitch(
            $account,
            $data['module'],
            $this->inlineNodeDataValidator->validate($data['module'], $data['data']),
        );
        $this->treeNodeWriteValidator->assertCanCreate(
            $callflow,
            $data['parent_path'],
            $data['branch'],
            $data['module'],
        );

        return $this->writeInlineTreeNode(
            $account,
            $callflow,
            $actor,
            'create',
            $data['parent_path'],
            $data['branch'],
            $data['module'],
            $settings,
            $ipAddress,
        );
    }

    /** @param array<string, mixed> $data */
    public function updateInlineTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        $settings = $this->inlineSettingsForSwitch(
            $account,
            $data['module'],
            $this->inlineNodeDataValidator->validate($data['module'], $data['data']),
        );
        $this->treeNodeWriteValidator->assertCanUpdate($callflow, $data['node_path'], $data['module']);

        return $this->writeInlineTreeNode(
            $account,
            $callflow,
            $actor,
            'update',
            $data['node_path'],
            null,
            $data['module'],
            $settings,
            $ipAddress,
        );
    }

    /**
     * @param  list<string>  $path
     * @param  array<string, mixed>  $settings
     */
    private function writeInlineTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        string $operation,
        array $path,
        ?string $branch,
        string $module,
        array $settings,
        ?string $ipAddress,
    ): SwitchCallflow {
        try {
            $snapshot = new CallflowSnapshot($this->gateway->writeInlineTreeNode(
                $account,
                $callflow->switch_resource_id,
                $operation,
                $path,
                $branch,
                $module,
                $settings,
            ));

            return DB::transaction(function () use (
                $account,
                $callflow,
                $actor,
                $operation,
                $path,
                $branch,
                $module,
                $ipAddress,
                $snapshot,
            ): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    $operation === 'create' ? 'callflow.inline_node_created' : 'callflow.inline_node_updated',
                    'succeeded',
                    $projected->switch_resource_id,
                    [
                        'callflow_id' => $projected->id,
                        'path' => $path,
                        'branch' => $branch,
                        'module' => $module,
                    ],
                    $ipAddress,
                    'callflow',
                );

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                $callflow,
                $operation === 'create' ? 'callflow.inline_node_create_failed' : 'callflow.inline_node_update_failed',
                ['path' => $path, 'branch' => $branch, 'module' => $module],
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function inlineSettingsForSwitch(
        SwitchAccount $account,
        string $module,
        array $settings,
    ): array {
        if ($module !== 'missed_call_alert') {
            return $settings;
        }

        /** @var list<array{type: string, id: string}> $recipients */
        $recipients = $settings['recipients'];
        $userIds = collect($recipients)
            ->where('type', 'user')
            ->pluck('id')
            ->unique()
            ->values();
        $resources = $account->extensions()
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(fn ($extension): array => [(string) $extension->id => $extension->switch_resource_id]);
        $errors = [];

        foreach ($recipients as $index => &$recipient) {
            if ($recipient['type'] !== 'user') {
                continue;
            }

            $resourceId = $resources->get($recipient['id']);

            if (! is_string($resourceId) || $resourceId === '') {
                $errors["data.recipients.$index.id"] = ['Select a synchronized extension in this account.'];

                continue;
            }

            $recipient['id'] = $resourceId;
        }
        unset($recipient);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $settings['recipients'] = $recipients;

        return $settings;
    }

    /** @param array<string, mixed> $data */
    public function updateTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        [$module, $resourceId] = $this->resolveDestination(
            $account,
            $callflow,
            $data['destination_type'],
            $data['destination_id'],
        );
        $this->editor->assertEditable($callflow);
        $this->treeNodeWriteValidator->assertCanUpdate($callflow, $data['node_path'], $module);

        return $this->writeTreeNode(
            $account,
            $callflow,
            $actor,
            'update',
            $data['node_path'],
            null,
            $module,
            $resourceId,
            $data,
            $ipAddress,
        );
    }

    /**
     * @param  list<string>  $path
     * @param  array<string, mixed>  $auditData
     */
    private function writeTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        string $operation,
        array $path,
        ?string $branch,
        string $module,
        string $resourceId,
        array $auditData,
        ?string $ipAddress,
    ): SwitchCallflow {
        try {
            $snapshot = new CallflowSnapshot($this->gateway->writeTreeNode(
                $account,
                $callflow->switch_resource_id,
                $operation,
                $path,
                $branch,
                $module,
                $resourceId,
            ));

            return DB::transaction(function () use (
                $account,
                $callflow,
                $actor,
                $operation,
                $path,
                $branch,
                $module,
                $auditData,
                $ipAddress,
                $snapshot,
            ): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    $operation === 'create' ? 'callflow.node_created' : 'callflow.node_updated',
                    'succeeded',
                    $projected->switch_resource_id,
                    [
                        'callflow_id' => $projected->id,
                        'path' => $path,
                        'branch' => $branch,
                        'module' => $module,
                        'destination_type' => $auditData['destination_type'],
                        'destination_id' => $auditData['destination_id'],
                    ],
                    $ipAddress,
                    'callflow',
                );

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                $callflow,
                $operation === 'create' ? 'callflow.node_create_failed' : 'callflow.node_update_failed',
                $auditData,
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
    }

    public function delete(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        ?string $ipAddress = null,
    ): void {
        $blockers = $this->deletionBlockers($account, $callflow);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['callflow' => $blockers]);
        }

        try {
            $this->gateway->delete($account, $callflow->switch_resource_id);
            DB::transaction(function () use ($account, $callflow, $actor, $ipAddress): void {
                $callflow->delete();
                $this->audit->record(
                    $actor,
                    $account,
                    'callflow.deleted',
                    'succeeded',
                    $callflow->switch_resource_id,
                    ['callflow_id' => $callflow->id, 'name' => $callflow->name],
                    $ipAddress,
                    'callflow',
                );
            });
        } catch (Throwable $exception) {
            $this->recordFailure($actor, $account, $callflow, 'callflow.delete_failed', [], $exception, $ipAddress);

            throw $exception;
        }
    }

    private function project(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        CallflowSnapshot $snapshot,
    ): SwitchCallflow {
        $callflow ??= SwitchCallflow::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $snapshot->id,
        ]);
        $ownerResourceId = $account->extensions()
            ->whereIn('extension', $snapshot->numbers)
            ->value('switch_resource_id');
        $extensionId = $ownerResourceId === null
            ? null
            : $account->extensions()
                ->where('switch_resource_id', $ownerResourceId)
                ->value('extension_id');

        $callflow->fill([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $snapshot->id,
            'switch_extension_id' => $extensionId,
            'owner_switch_resource_id' => $ownerResourceId,
            'name' => $snapshot->name,
            'numbers' => $snapshot->numbers,
            'patterns' => $snapshot->patterns,
            'flags' => $snapshot->flags,
            'modules' => $snapshot->modules,
            'root_module' => $snapshot->flow?->module,
            'node_count' => $snapshot->nodeCount,
            'max_depth' => $snapshot->maxDepth,
            'is_feature_code' => $snapshot->featureCodeName !== null || $snapshot->featureCodeNumber !== null,
            'feature_code_name' => $snapshot->featureCodeName,
            'feature_code_number' => $snapshot->featureCodeNumber,
            'flow_structure' => ($flow = $this->references->resolve(
                $account,
                is_array($snapshot->data['flow'] ?? null) ? $snapshot->data['flow'] : null,
            )) === null ? null : $this->jsonNormalizer->flow($flow),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $callflow->exists ? $callflow->projection_version + 1 : 1,
            'switch_json' => $this->jsonNormalizer->document(
                $this->redactSensitiveData->handle($snapshot->toArray()),
            ),
        ]);
        $callflow->deleted_at = null;
        $callflow->save();

        return $callflow;
    }

    /** @param list<string> $selectedPhoneNumberIds */
    private function reconcilePhoneNumbers(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        array $selectedPhoneNumberIds,
    ): void {
        $account->phoneNumbers()
            ->where('assigned_callflow_id', $callflow->getKey())
            ->when(
                $selectedPhoneNumberIds !== [],
                fn ($query) => $query->whereNotIn('id', $selectedPhoneNumberIds),
            )
            ->update(['assigned_callflow_id' => null]);
        $account->phoneNumbers()
            ->whereIn('id', $selectedPhoneNumberIds)
            ->update(['assigned_callflow_id' => $callflow->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{string, ?string, list<string>}
     */
    private function destination(SwitchAccount $account, ?SwitchCallflow $callflow, array $data): array
    {
        if (($data['destination_type'] ?? null) === 'temporal_rules') {
            $ruleIds = is_array($data['temporal_rule_ids'] ?? null)
                ? array_values($data['temporal_rule_ids'])
                : [];
            $rules = $account->temporalRules()->whereIn('id', $ruleIds)->get()->keyBy('id');

            if ($ruleIds === [] || $rules->count() !== count($ruleIds)) {
                throw ValidationException::withMessages([
                    'temporal_rule_ids' => ['Select one or more available Temporal Rules.'],
                ]);
            }

            return [
                'temporal_route',
                null,
                array_map(
                    fn (string $id): string => $rules->get($id)->switch_resource_id,
                    $ruleIds,
                ),
            ];
        }

        [$module, $resourceId] = $this->resolveDestination(
            $account,
            $callflow,
            $data['destination_type'],
            $data['destination_id'],
        );

        return [$module, $resourceId, []];
    }

    /** @return array{?string, ?string} */
    private function optionalDestination(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        mixed $type,
        mixed $id,
    ): array {
        if ($type === null && $id === null) {
            return [null, null];
        }

        if (! is_string($type) || ! is_string($id)) {
            throw ValidationException::withMessages([
                'fallback_destination_id' => ['Select a complete fallback destination.'],
            ]);
        }

        return $this->resolveDestination($account, $callflow, $type, $id);
    }

    /** @return array{string, string} */
    private function resolveDestination(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        string $type,
        string $id,
    ): array {
        $target = match ($type) {
            'extension' => $account->extensions()->where('id', $id)->firstOrFail(),
            'device' => $account->devices()->where('id', $id)->firstOrFail(),
            'voicemail' => $account->voicemailBoxes()->where('id', $id)->firstOrFail(),
            'callflow' => $account->callflows()
                ->when($callflow !== null, fn ($query) => $query->whereKeyNot($callflow->getKey()))
                ->where('id', $id)
                ->firstOrFail(),
            'media' => $account->media()->where('id', $id)->firstOrFail(),
            'directory' => $account->directories()->where('id', $id)->firstOrFail(),
            'group' => $account->groups()->where('id', $id)->firstOrFail(),
            'queue' => $account->queues()->where('id', $id)->firstOrFail(),
            'menu' => $account->menus()->where('id', $id)->firstOrFail(),
            'conference' => $account->conferences()->where('id', $id)->firstOrFail(),
            'fax_box' => $account->faxBoxes()->where('id', $id)->firstOrFail(),
            'temporal_rule_set' => $account->temporalRuleSets()->where('id', $id)->firstOrFail(),
        };

        if ($type === 'temporal_rule_set'
            && ! $target->rules()->whereNotNull('switch_temporal_rule_id')->exists()) {
            throw ValidationException::withMessages([
                'destination_id' => ['Synchronize a schedule with at least one resolved rule before routing calls through it.'],
            ]);
        }

        return [
            match ($type) {
                'extension' => 'user',
                'device' => 'device',
                'voicemail' => 'voicemail',
                'callflow' => 'callflow',
                'media' => 'play',
                'directory' => 'directory',
                'group' => 'group',
                'queue' => 'acdc_member',
                'menu' => 'menu',
                'conference' => 'conference',
                'fax_box' => 'faxbox',
                'temporal_rule_set' => 'temporal_route',
            },
            $target->switch_resource_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, module: ?string, resource_id: ?string}>
     */
    private function menuBranchOperations(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        string $rootModule,
        array $data,
    ): array {
        if (! (bool) ($data['manage_menu_branches'] ?? false)) {
            return [];
        }

        if ($rootModule !== 'menu') {
            throw ValidationException::withMessages([
                'menu_branches' => ['Menu key routes require a Menu / IVR root destination.'],
            ]);
        }

        $desired = collect(is_array($data['menu_branches'] ?? null) ? $data['menu_branches'] : [])
            ->keyBy('key');

        if ($callflow !== null) {
            $this->editor->assertMenuBranchesEditable($callflow, $desired->keys()->all());
        }

        $current = collect($this->editor->menuBranches($callflow)['branches'])->keyBy('key');
        $operations = [];

        foreach ($current as $key => $branch) {
            if (! is_array($branch) || ! $branch['editable']) {
                continue;
            }

            $selection = $desired->get($key);

            if (! is_array($selection)) {
                if ($branch['target'] !== null) {
                    $operations[] = ['key' => (string) $key, 'module' => null, 'resource_id' => null];
                }

                continue;
            }

            [$module, $resourceId] = $this->resolveDestination(
                $account,
                $callflow,
                $selection['destination_type'],
                $selection['destination_id'],
            );
            $operations[] = [
                'key' => (string) $key,
                'module' => $module,
                'resource_id' => $resourceId,
            ];
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, module: ?string, resource_id: ?string}>
     */
    private function temporalBranchOperations(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        string $rootModule,
        array $data,
    ): array {
        if (! (bool) ($data['manage_temporal_match'] ?? false)) {
            return [];
        }

        if ($rootModule !== 'temporal_route') {
            throw ValidationException::withMessages([
                'temporal_match_destination_id' => ['A schedule match route requires a Business Hours / Schedule root destination.'],
            ]);
        }

        if ($callflow !== null) {
            $this->editor->assertTemporalMatchEditable($callflow);
        }

        $type = $data['temporal_match_destination_type'] ?? null;
        $id = $data['temporal_match_destination_id'] ?? null;

        if ($type === null && $id === null) {
            if ($callflow === null) {
                throw ValidationException::withMessages([
                    'temporal_match_destination_id' => ['Select a destination for calls that match the schedule.'],
                ]);
            }

            return [[
                'key' => 'rule_set',
                'module' => null,
                'resource_id' => null,
            ]];
        }

        if (! is_string($type) || ! is_string($id)) {
            throw ValidationException::withMessages([
                'temporal_match_destination_id' => ['Select a complete schedule match destination.'],
            ]);
        }

        [$module, $resourceId] = $this->resolveDestination($account, $callflow, $type, $id);

        return [[
            'key' => 'rule_set',
            'module' => $module,
            'resource_id' => $resourceId,
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, module: ?string, resource_id: ?string}>
     */
    private function directTemporalBranchOperations(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        array $data,
    ): array {
        $usesDirectTemporalRules = ($data['destination_type'] ?? null) === 'temporal_rules';
        $currentTemporalRules = $callflow === null
            ? []
            : (is_array($callflow->flow_structure['temporal_rules'] ?? null)
                ? $callflow->flow_structure['temporal_rules']
                : []);

        if (! $usesDirectTemporalRules && $currentTemporalRules === []) {
            return [];
        }

        if ($callflow !== null) {
            $this->editor->assertDirectTemporalRoutesEditable($account, $callflow);
        }

        if (! $usesDirectTemporalRules) {
            $currentRuleIds = collect($currentTemporalRules)
                ->pluck('id')
                ->filter(fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();
            $currentRules = $account->temporalRules()
                ->whereIn('id', $currentRuleIds)
                ->get()
                ->keyBy('id');

            if ($currentRules->count() !== count($currentRuleIds)) {
                throw ValidationException::withMessages([
                    'temporal_rule_ids' => ['Synchronize every referenced Temporal Rule before changing this route.'],
                ]);
            }

            return array_map(
                fn (string $ruleId): array => [
                    'key' => $currentRules->get($ruleId)->switch_resource_id,
                    'module' => null,
                    'resource_id' => null,
                ],
                $currentRuleIds,
            );
        }

        $selectedIds = is_array($data['temporal_rule_ids'] ?? null)
            ? array_values($data['temporal_rule_ids'])
            : [];
        $routes = collect(is_array($data['temporal_rule_routes'] ?? null)
            ? $data['temporal_rule_routes']
            : [])->keyBy('rule_id');

        if ($routes->count() !== count($selectedIds)
            || collect($selectedIds)->contains(fn (mixed $id): bool => ! $routes->has($id))) {
            throw ValidationException::withMessages([
                'temporal_rule_routes' => ['Configure exactly one match destination for each selected Temporal Rule.'],
            ]);
        }

        $allRuleIds = array_values(array_unique([
            ...$selectedIds,
            ...collect($currentTemporalRules)
                ->pluck('id')
                ->filter(fn (mixed $id): bool => is_string($id))
                ->values()
                ->all(),
        ]));
        $rules = $account->temporalRules()->whereIn('id', $allRuleIds)->get()->keyBy('id');

        if ($rules->count() !== count($allRuleIds)) {
            throw ValidationException::withMessages([
                'temporal_rule_ids' => ['Synchronize every referenced Temporal Rule before editing this route.'],
            ]);
        }

        $operations = [];

        foreach ($allRuleIds as $ruleId) {
            $rule = $rules->get($ruleId);

            if (! in_array($ruleId, $selectedIds, true)) {
                $operations[] = [
                    'key' => $rule->switch_resource_id,
                    'module' => null,
                    'resource_id' => null,
                ];
            }
        }

        foreach ($selectedIds as $ruleId) {
            $selection = $routes->get($ruleId);

            if (! is_array($selection)) {
                continue;
            }

            [$module, $resourceId] = $this->resolveDestination(
                $account,
                $callflow,
                $selection['destination_type'],
                $selection['destination_id'],
            );
            $operations[] = [
                'key' => $rules->get($ruleId)->switch_resource_id,
                'module' => $module,
                'resource_id' => $resourceId,
            ];
        }

        return $operations;
    }

    /**
     * @param  list<string>  $selectedIds
     * @return array{list<string>, list<string>}
     */
    private function phoneNumberSelection(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        array $selectedIds,
    ): array {
        $selected = $account->phoneNumbers()->whereIn('id', $selectedIds)->get();

        if ($selected->count() !== count($selectedIds)) {
            throw ValidationException::withMessages([
                'phone_number_ids' => ['One or more selected phone numbers are unavailable for this account.'],
            ]);
        }

        $conflicts = $selected->filter(fn ($phoneNumber): bool => $phoneNumber->assigned_callflow_id !== null
            && ($callflow === null || $phoneNumber->assigned_callflow_id !== $callflow->getKey()));

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'phone_number_ids' => [sprintf(
                    'The following phone numbers are already assigned to another route: %s.',
                    $conflicts->pluck('number')->implode(', '),
                )],
            ]);
        }

        return [
            $selected->pluck('number')->values()->all(),
            $account->phoneNumbers()->pluck('number')->values()->all(),
        ];
    }

    /** @return list<string> */
    private function deletionBlockers(SwitchAccount $account, SwitchCallflow $callflow): array
    {
        $blockers = [];

        if ($callflow->is_feature_code) {
            $blockers[] = 'Feature-code routes cannot be deleted from the guided editor.';
        }

        if ($callflow->switch_extension_id !== null) {
            $blockers[] = 'This route belongs to an extension and must be removed through the extension workflow.';
        }

        if ($callflow->phoneNumbers()->exists()) {
            $blockers[] = 'Remove all assigned phone numbers before deleting this route.';
        }

        foreach ($account->callflows()->whereKeyNot($callflow->getKey())->get() as $candidate) {
            if ($this->containsReference($candidate->flow_structure, $callflow->id)) {
                $blockers[] = sprintf(
                    'This route is referenced by %s.',
                    $candidate->name ?? ($candidate->numbers[0] ?? 'another route'),
                );
            }
        }

        return array_values(array_unique($blockers));
    }

    /** @param array<string, mixed>|null $node */
    private function containsReference(?array $node, string $targetId): bool
    {
        if ($node === null) {
            return false;
        }

        if (($node['module'] ?? null) === 'callflow') {
            $resolvedId = is_array($node['target'] ?? null) ? ($node['target']['id'] ?? null) : null;

            if ($resolvedId === $targetId || ($node['reference_status'] ?? null) === 'unresolved') {
                return true;
            }
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child) && $this->containsReference($child, $targetId)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function recordSuccess(
        User $actor,
        SwitchAccount $account,
        SwitchCallflow $callflow,
        string $action,
        array $data,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'succeeded',
            $callflow->switch_resource_id,
            [
                'callflow_id' => $callflow->id,
                'destination_type' => $data['destination_type'],
                'destination_id' => $data['destination_id'] ?? null,
                'temporal_rule_ids' => $data['temporal_rule_ids'] ?? [],
                'name' => $data['name'],
                'phone_number_ids' => $data['phone_number_ids'],
            ],
            $ipAddress,
            'callflow',
        );
    }

    /** @param array<string, mixed> $data */
    private function recordFailure(
        User $actor,
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        string $action,
        array $data,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            $action,
            'failed',
            $callflow?->switch_resource_id,
            [
                'callflow_id' => $callflow?->id,
                'destination_type' => $data['destination_type'] ?? null,
                'destination_id' => $data['destination_id'] ?? null,
                'phone_number_ids' => $data['phone_number_ids'] ?? [],
                'error_type' => $exception::class,
            ],
            $ipAddress,
            'callflow',
        );
    }

    private function load(SwitchCallflow $callflow): SwitchCallflow
    {
        return $callflow->load([
            'extension:extension_id,id,display_name,extension',
            'phoneNumbers:phone_number_id,id,assigned_callflow_id,number,state',
        ]);
    }
}
