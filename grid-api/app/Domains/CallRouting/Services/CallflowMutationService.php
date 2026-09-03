<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Contracts\SwitchCallflowEntryPointGateway;
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
        private readonly PivotEndpointRegistry $pivotEndpoints,
        private readonly WebhookEndpointRegistry $webhookEndpoints,
        private readonly DisaAccessPolicyRegistry $disaPolicies,
        private readonly CarrierRouteRegistry $carrierRoutes,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        [$module, $resourceId, $temporalRuleIds, $destinationSettings] = $this->destination(
            $account,
            null,
            $data,
        );
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
        [$assignedPhoneNumbers, $knownPhoneNumbers] = $this->phoneNumberSelection(
            $account,
            null,
            $data['phone_number_ids'],
        );
        [$assignedExtensionNumbers] = $this->extensionNumberSelection(
            $account,
            null,
            $data['extension_numbers'] ?? [],
            $knownPhoneNumbers,
        );
        $assignedEntryNumbers = array_values(array_unique([
            ...$assignedExtensionNumbers,
            ...$assignedPhoneNumbers,
        ]));

        try {
            $snapshot = new CallflowSnapshot($this->gateway->create(
                $account,
                $data['name'],
                $module,
                $resourceId,
                $assignedEntryNumbers,
                $fallbackModule,
                $fallbackResourceId,
                [
                    ...$menuBranchOperations,
                    ...$temporalBranchOperations,
                    ...$directTemporalBranchOperations,
                ],
                $temporalRuleIds,
                $destinationSettings,
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
        [$assignedExtensionNumbers, $knownExtensionNumbers] = $this->extensionNumberSelection(
            $account,
            $callflow,
            $data['extension_numbers'] ?? [],
            $knownPhoneNumbers,
        );
        $assignedEntryNumbers = array_values(array_unique([
            ...$assignedExtensionNumbers,
            ...$assignedPhoneNumbers,
        ]));
        $knownEntryNumbers = array_values(array_unique([
            ...$knownExtensionNumbers,
            ...$knownPhoneNumbers,
        ]));
        $this->assertEntryNumberOrPatternRemains(
            $callflow,
            $assignedEntryNumbers,
            $knownEntryNumbers,
        );

        try {
            $snapshot = new CallflowSnapshot($this->gateway->updateDestination(
                $account,
                $callflow->switch_resource_id,
                $module,
                $resourceId,
                $data['name'],
                $assignedEntryNumbers,
                $knownEntryNumbers,
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
    public function updateEntryPoints(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        SwitchCallflowEntryPointGateway $entryPointGateway,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        [$assignedPhoneNumbers, $knownPhoneNumbers] = $this->phoneNumberSelection(
            $account,
            $callflow,
            $data['phone_number_ids'],
        );
        [$assignedExtensionNumbers, $knownExtensionNumbers] = $this->extensionNumberSelection(
            $account,
            $callflow,
            $data['extension_numbers'],
            $knownPhoneNumbers,
        );
        $assignedEntryNumbers = array_values(array_unique([
            ...$assignedExtensionNumbers,
            ...$assignedPhoneNumbers,
        ]));
        $knownEntryNumbers = array_values(array_unique([
            ...$knownExtensionNumbers,
            ...$knownPhoneNumbers,
        ]));
        $this->assertEntryNumberOrPatternRemains(
            $callflow,
            $assignedEntryNumbers,
            $knownEntryNumbers,
        );

        try {
            $snapshot = new CallflowSnapshot($entryPointGateway->updateEntryPoints(
                $account,
                $callflow->switch_resource_id,
                $assignedEntryNumbers,
                $knownEntryNumbers,
            ));

            return DB::transaction(function () use ($account, $callflow, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->reconcilePhoneNumbers($account, $projected, $data['phone_number_ids']);
                $this->recordSuccess(
                    $actor,
                    $account,
                    $projected,
                    'callflow.entry_points_updated',
                    $data,
                    $ipAddress,
                );

                return $this->load($projected);
            });
        } catch (Throwable $exception) {
            $this->recordFailure(
                $actor,
                $account,
                $callflow,
                'callflow.entry_points_update_failed',
                $data,
                $exception,
                $ipAddress,
            );

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
            is_array($data['data'] ?? null) ? $data['data'] : null,
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
            $data['placement'] ?? 'append',
            (bool) ($data['confirm_replace'] ?? false),
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
            $data['placement'] ?? 'append',
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
        $editingInlineRoot = $data['node_path'] === []
            && in_array($data['module'], ['ring_group', 'dynamic_cid'], true);

        if (! $editingInlineRoot) {
            $this->editor->assertEditable($callflow);
        }

        $settings = $this->inlineSettingsForSwitch(
            $account,
            $data['module'],
            $this->inlineNodeDataValidator->validate($data['module'], $data['data']),
        );
        $this->treeNodeWriteValidator->assertCanUpdate(
            $callflow,
            $data['node_path'],
            $data['module'],
            $settings,
        );

        return $this->writeInlineTreeNode(
            $account,
            $callflow,
            $actor,
            'update',
            $data['node_path'],
            null,
            $data['module'],
            $settings,
            'append',
            $ipAddress,
        );
    }

    /** @param array<string, mixed> $data */
    public function deleteTreeNode(
        SwitchAccount $account,
        SwitchCallflow $callflow,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchCallflow {
        $this->editor->assertEditable($callflow);
        $this->treeNodeWriteValidator->assertCanDelete($callflow, $data['node_path']);

        try {
            $snapshot = new CallflowSnapshot($this->gateway->deleteTreeNode(
                $account,
                $callflow->switch_resource_id,
                $data['node_path'],
            ));

            return DB::transaction(function () use ($account, $callflow, $actor, $data, $ipAddress, $snapshot): SwitchCallflow {
                $projected = $this->project($account, $callflow, $snapshot);
                $this->audit->record(
                    $actor,
                    $account,
                    'callflow.node_deleted',
                    'succeeded',
                    $projected->switch_resource_id,
                    [
                        'callflow_id' => $projected->id,
                        'path' => $data['node_path'],
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
                'callflow.node_delete_failed',
                ['node_path' => $data['node_path']],
                $exception,
                $ipAddress,
            );

            throw $exception;
        }
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
        string $placement,
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
                $placement,
            ));

            return DB::transaction(function () use (
                $account,
                $callflow,
                $actor,
                $operation,
                $path,
                $branch,
                $module,
                $placement,
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
                        'placement' => $placement,
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
                ['path' => $path, 'branch' => $branch, 'module' => $module, 'placement' => $placement],
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
        if ($module === 'pivot') {
            return $this->pivotEndpoints->settingsForSwitch($account, $settings);
        }

        if ($module === 'dynamic_cid') {
            return $this->dynamicCidSettingsForSwitch($account, $settings);
        }

        if ($module === 'webhook') {
            return $this->webhookEndpoints->settingsForSwitch($account, $settings);
        }

        if ($module === 'disa') {
            return $this->disaPolicies->settingsForSwitch($account, $settings);
        }

        if (in_array($module, ['offnet', 'resources'], true)) {
            return $this->carrierRoutes->settingsForSwitch($account, $module, $settings);
        }

        if ($module === 'check_cid') {
            return $this->checkCidSettingsForSwitch($account, $settings);
        }

        if ($module === 'cidlistmatch') {
            return $this->callerIdListMatchSettingsForSwitch($account, $settings);
        }

        if ($module === 'missed_call_alert') {
            return $this->missedCallAlertSettingsForSwitch($account, $settings);
        }

        if ($module === 'temporal_route') {
            return $this->temporalOperationSettingsForSwitch($account, $settings);
        }

        if ($module === 'ring_group_toggle') {
            return $this->ringGroupToggleSettingsForSwitch($account, $settings);
        }

        if ($module === 'acdc_queue') {
            return $this->acdcQueueSettingsForSwitch($account, $settings);
        }

        if ($module === 'group_pickup') {
            return $this->groupPickupSettingsForSwitch($account, $settings);
        }

        if ($module === 'page_group') {
            return $this->pageGroupSettingsForSwitch($account, $settings);
        }

        if ($module === 'ring_group') {
            return $this->ringGroupSettingsForSwitch($account, $settings);
        }

        if ($module === 'receive_fax') {
            return $this->receiveFaxSettingsForSwitch($account, $settings);
        }

        if ($module === 'response') {
            if (! array_key_exists('media_id', $settings)) {
                return $settings;
            }

            $mediaId = $settings['media_id'];
            $mediaResourceId = $mediaId === null
                ? null
                : $account->media()->where('id', $mediaId)->value('switch_resource_id');

            if ($mediaId !== null && (! is_string($mediaResourceId) || $mediaResourceId === '')) {
                throw ValidationException::withMessages([
                    'data.media_id' => ['Select synchronized media owned by this account.'],
                ]);
            }

            return [
                'code' => $settings['code'],
                'message' => $settings['message'],
                'media' => $mediaResourceId,
                'skip_module' => $settings['skip_module'],
            ];
        }

        if ($module === 'conference') {
            return ['skip_module' => $settings['skip_module']];
        }

        return $settings;
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function dynamicCidSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $phoneNumber = $account->phoneNumbers()
            ->where('id', $settings['phone_number_id'])
            ->first();

        if ($phoneNumber === null || ! is_string($phoneNumber->number) || $phoneNumber->number === '') {
            throw ValidationException::withMessages([
                'data.phone_number_id' => ['Select a synchronized phone number owned by this account.'],
            ]);
        }

        $callerId = ['number' => $phoneNumber->number];
        $name = trim((string) ($settings['caller_id_name'] ?? ''));

        if ($name !== '') {
            $callerId['name'] = $name;
        }

        return [
            'action' => 'static',
            'caller_id' => $callerId,
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function groupPickupSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        [$relation, $switchKey, $label] = match ($settings['target_type']) {
            'extension' => [$account->extensions(), 'user_id', 'extension'],
            'device' => [$account->devices(), 'device_id', 'device'],
            'group' => [$account->groups(), 'group_id', 'group'],
        };
        $resourceId = $relation
            ->where('id', $settings['target_id'])
            ->value('switch_resource_id');

        if (! is_string($resourceId) || $resourceId === '') {
            throw ValidationException::withMessages([
                'data.target_id' => ["Select a synchronized {$label} in this account."],
            ]);
        }

        return [
            $switchKey => $resourceId,
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function receiveFaxSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $resourceId = $account->extensions()
            ->where('id', $settings['owner_id'])
            ->value('switch_resource_id');

        if (! is_string($resourceId) || $resourceId === '') {
            throw ValidationException::withMessages([
                'data.owner_id' => ['Select a synchronized extension in this account.'],
            ]);
        }

        return [
            'owner_id' => $resourceId,
            'media' => ['fax_option' => $settings['fax_option']],
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function pageGroupSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $endpoints = collect($settings['endpoints'])->map(function (array $endpoint) use ($account): array {
            [$publicType, $publicId] = $this->ringGroupPublicTarget($endpoint);
            [$relation, $switchType] = match ($publicType) {
                'device' => [$account->devices(), 'device'],
                'extension' => [$account->extensions(), 'user'],
                'group' => [$account->groups(), 'group'],
            };
            $resourceId = $relation->where('id', $publicId)->value('switch_resource_id');

            if (! is_string($resourceId) || $resourceId === '') {
                throw ValidationException::withMessages([
                    'data.endpoints' => ['Select only synchronized Devices, Extensions, or Groups in this account.'],
                ]);
            }

            return [
                'endpoint_type' => $switchType,
                'id' => $resourceId,
                'delay' => $endpoint['delay'],
                'timeout' => $endpoint['timeout'],
            ];
        });

        return [
            'audio' => $settings['audio'],
            'endpoints' => $endpoints->all(),
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function ringGroupSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $endpoints = collect($settings['endpoints']);
        $resolvedEndpoints = $endpoints->map(function (array $endpoint) use ($account): array {
            [$publicType, $publicId] = $this->ringGroupPublicTarget($endpoint);
            [$relation, $switchType] = match ($publicType) {
                'device' => [$account->devices(), 'device'],
                'extension' => [$account->extensions(), 'user'],
                'group' => [$account->groups(), 'group'],
            };
            $resourceId = $relation->where('id', $publicId)->value('switch_resource_id');

            if (! is_string($resourceId) || $resourceId === '') {
                throw ValidationException::withMessages([
                    'data.endpoints' => ['Select only synchronized Devices, Extensions, or Groups in this account.'],
                ]);
            }

            return [
                'endpoint_type' => $switchType,
                'id' => $resourceId,
                'delay' => $endpoint['delay'],
                'timeout' => $endpoint['timeout'],
                ...(isset($endpoint['weight']) ? ['weight' => $endpoint['weight']] : []),
            ];
        });

        $ringback = null;

        if ($settings['ringback_media_id'] !== null) {
            $media = $account->media()
                ->where('id', $settings['ringback_media_id'])
                ->where('streamable', true)
                ->first();

            if ($media === null
                || ! is_string($media->switch_resource_id)
                || $media->switch_resource_id === ''
                || ! is_string($media->content_type)
                || ! str_starts_with($media->content_type, 'audio/')) {
                throw ValidationException::withMessages([
                    'data.ringback_media_id' => ['Select streamable audio media from this account.'],
                ]);
            }

            $ringback = $media->switch_resource_id;
        }

        return [
            'strategy' => $settings['strategy'],
            'endpoints' => $resolvedEndpoints->all(),
            'repeats' => $settings['repeats'],
            'timeout' => RingGroupPolicy::attemptTimeout($settings['strategy'], $settings['endpoints']),
            'ignore_forward' => $settings['ignore_forward'],
            'fail_on_single_reject' => $settings['fail_on_single_reject'],
            'ringback' => $ringback,
            'ringtones' => [
                'internal' => $settings['ringtone_internal'],
                'external' => $settings['ringtone_external'],
            ],
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $endpoint @return array{string, string} */
    private function ringGroupPublicTarget(array $endpoint): array
    {
        foreach (['device_id' => 'device', 'extension_id' => 'extension', 'group_id' => 'group'] as $key => $type) {
            if (is_string($endpoint[$key] ?? null) && $endpoint[$key] !== '') {
                return [$type, $endpoint[$key]];
            }
        }

        throw ValidationException::withMessages([
            'data.endpoints' => ['Select exactly one synchronized Device, Extension, or Group.'],
        ]);
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function temporalOperationSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $publicIds = collect($settings['rules'])->unique()->values();
        $resources = $account->temporalRules()
            ->whereIn('id', $publicIds)
            ->get()
            ->mapWithKeys(fn ($rule): array => [(string) $rule->id => $rule->switch_resource_id]);
        $missing = $publicIds->reject(fn (string $id): bool => $resources->has($id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'data.rules' => ['Select only synchronized temporal rules in this account.'],
            ]);
        }

        return [
            'action' => $settings['action'],
            'rules' => $publicIds->map(fn (string $id): string => $resources->get($id))->all(),
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function ringGroupToggleSettingsForSwitch(
        SwitchAccount $account,
        array $settings,
    ): array {
        $target = $account->callflows()
            ->where('id', $settings['callflow_id'])
            ->first();

        if ($target === null
            || ! $target->canBeRingGroupToggleTarget()
            || ! is_string($target->switch_resource_id)
            || $target->switch_resource_id === '') {
            throw ValidationException::withMessages([
                'data.callflow_id' => ['Select another synchronized callflow containing a Ring Group in this account.'],
            ]);
        }

        return [
            'action' => $settings['action'],
            'callflow_id' => $target->switch_resource_id,
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function acdcQueueSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $resourceId = $account->queues()
            ->where('id', $settings['queue_id'])
            ->value('switch_resource_id');

        if (! is_string($resourceId) || $resourceId === '') {
            throw ValidationException::withMessages([
                'data.queue_id' => ['Select a synchronized queue in this account.'],
            ]);
        }

        return [
            'action' => $settings['action'],
            'id' => $resourceId,
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function callerIdListMatchSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $list = $account->callerIdLists()->where('id', $settings['caller_id_list_id'])->first();

        if ($list === null) {
            throw ValidationException::withMessages([
                'data.caller_id_list_id' => ['Select a synchronized Caller-ID List in this account.'],
            ]);
        }

        return [
            'id' => $list->switch_resource_id,
            'skip_module' => $settings['skip_module'],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function missedCallAlertSettingsForSwitch(SwitchAccount $account, array $settings): array
    {

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

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function checkCidSettingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $publicUserId = $settings['user_id'];
        $resourceId = null;

        if (is_string($publicUserId)) {
            $resourceId = $account->extensions()
                ->where('id', $publicUserId)
                ->value('switch_resource_id');

            if (! is_string($resourceId) || $resourceId === '') {
                throw ValidationException::withMessages([
                    'data.user_id' => ['Select a synchronized extension in this account.'],
                ]);
            }
        }

        return [
            'regex' => $settings['regex'],
            'use_absolute_mode' => false,
            'caller_id' => $resourceId === null ? null : [
                'external' => [
                    'name' => $settings['external_caller_id_name'],
                    'number' => $settings['external_caller_id_number'],
                ],
            ],
            'user_id' => $resourceId,
            'skip_module' => $settings['skip_module'],
        ];
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
            is_array($data['data'] ?? null) ? $data['data'] : null,
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
        ?array $settings,
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
     * @return array{string, ?string, list<string>, ?array<string, mixed>}
     */
    private function destination(SwitchAccount $account, ?SwitchCallflow $callflow, array $data): array
    {
        if (is_array($data['root_action'] ?? null)) {
            $module = $data['root_action']['module'] ?? null;
            $settings = $data['root_action']['data'] ?? null;

            if ($callflow !== null
                || ! in_array($module, ['ring_group', 'call_forward', 'dynamic_cid', 'pivot'], true)
                || ! is_array($settings)) {
                throw ValidationException::withMessages([
                    'root_action' => ['Select a supported inline root action.'],
                ]);
            }

            return [
                $module,
                null,
                [],
                $this->inlineSettingsForSwitch(
                    $account,
                    $module,
                    $this->inlineNodeDataValidator->validate($module, $settings),
                ),
            ];
        }

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
                null,
            ];
        }

        [$module, $resourceId] = $this->resolveDestination(
            $account,
            $callflow,
            $data['destination_type'],
            $data['destination_id'],
        );

        $destinationSettings = in_array($data['destination_type'], ['extension', 'device'], true)
            && is_array($data['destination_data'] ?? null)
                ? $data['destination_data']
                : null;

        return [$module, $resourceId, [], $destinationSettings];
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

    /**
     * Resolve editable internal aliases while preserving workflow-owned and unsupported entries.
     *
     * @param  list<string>  $selectedNumbers
     * @param  list<string>  $knownPhoneNumbers
     * @return array{list<string>, list<string>}
     */
    private function extensionNumberSelection(
        SwitchAccount $account,
        ?SwitchCallflow $callflow,
        array $selectedNumbers,
        array $knownPhoneNumbers,
    ): array {
        $selectedNumbers = array_values(array_unique($selectedNumbers));

        $managedExtensionConflict = $account->extensions()
            ->when(
                $callflow?->switch_extension_id !== null,
                fn ($query) => $query->whereKeyNot($callflow->switch_extension_id),
            )
            ->whereIn('extension', $selectedNumbers)
            ->pluck('extension')
            ->all();

        if ($managedExtensionConflict !== []) {
            throw ValidationException::withMessages([
                'extension_numbers' => [sprintf(
                    'The following extension numbers are managed by another extension: %s.',
                    implode(', ', $managedExtensionConflict),
                )],
            ]);
        }

        $inventoryConflicts = array_values(array_intersect($selectedNumbers, $knownPhoneNumbers));

        if ($inventoryConflicts !== []) {
            throw ValidationException::withMessages([
                'extension_numbers' => [sprintf(
                    'Use the phone-number inventory selector for: %s.',
                    implode(', ', $inventoryConflicts),
                )],
            ]);
        }

        $routeConflict = $account->callflows()
            ->when($callflow !== null, fn ($query) => $query->whereKeyNot($callflow->getKey()))
            ->get(['callflow_id', 'name', 'numbers'])
            ->first(fn (SwitchCallflow $candidate): bool => collect($candidate->numbers ?? [])
                ->contains(fn (mixed $number): bool => is_string($number)
                    && in_array($number, $selectedNumbers, true)));

        if ($routeConflict !== null) {
            $conflictingNumbers = array_values(array_intersect(
                $selectedNumbers,
                array_filter($routeConflict->numbers ?? [], fn (mixed $number): bool => is_string($number)),
            ));

            throw ValidationException::withMessages([
                'extension_numbers' => [sprintf(
                    'The following extension numbers already enter %s: %s.',
                    $routeConflict->name ?? 'another callflow',
                    implode(', ', $conflictingNumbers),
                )],
            ]);
        }

        if ($callflow === null) {
            return [$selectedNumbers, []];
        }

        $primaryExtension = $callflow->extension?->extension;
        $knownExtensionNumbers = collect($callflow->numbers ?? [])
            ->filter(fn (mixed $number): bool => is_string($number)
                && $number !== $primaryExtension
                && ! in_array($number, $knownPhoneNumbers, true)
                && preg_match('/^[0-9]{2,15}$/', $number) === 1)
            ->values()
            ->all();

        return [$selectedNumbers, $knownExtensionNumbers];
    }

    /**
     * Switch rejects callflows that have neither an entry number nor a pattern.
     * Preserve workflow-owned entries, but fail locally before sending an invalid write.
     *
     * @param  list<string>  $assignedEntryNumbers
     * @param  list<string>  $knownEntryNumbers
     */
    private function assertEntryNumberOrPatternRemains(
        SwitchCallflow $callflow,
        array $assignedEntryNumbers,
        array $knownEntryNumbers,
    ): void {
        $hasPattern = collect($callflow->patterns ?? [])->contains(
            fn (mixed $pattern): bool => is_string($pattern) && trim($pattern) !== '',
        );
        $hasPreservedEntry = collect($callflow->numbers ?? [])->contains(
            fn (mixed $number): bool => is_string($number)
                && trim($number) !== ''
                && ! in_array($number, $knownEntryNumbers, true),
        );

        if ($assignedEntryNumbers === [] && ! $hasPattern && ! $hasPreservedEntry) {
            throw ValidationException::withMessages([
                'extension_numbers' => [
                    'Keep at least one extension or phone number because Switch callflows require a number or pattern.',
                ],
            ]);
        }
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
                'destination_type' => $data['destination_type'] ?? null,
                'destination_id' => $data['destination_id'] ?? null,
                'temporal_rule_ids' => $data['temporal_rule_ids'] ?? [],
                'name' => $data['name'] ?? $callflow->name,
                'phone_number_ids' => $data['phone_number_ids'] ?? [],
                'extension_numbers' => $data['extension_numbers'] ?? [],
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
                'extension_numbers' => $data['extension_numbers'] ?? [],
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
