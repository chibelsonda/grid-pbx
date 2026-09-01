<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  ArrowsRightLeftIcon,
  PencilSquareIcon,
  ShieldCheckIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { findCallflowAction, type CallflowAction } from '../catalog/callflowActionCatalog'
import {
  availableCallflowBranches,
  callflowPalettePlacement,
  canAddCallflowChild,
} from '../services/callflowTreeBranches'
import CallflowActionPalette from './CallflowActionPalette.vue'
import CallflowDiagram from './CallflowDiagram.vue'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'
import CallflowRouteSummary from './CallflowRouteSummary.vue'
import CallflowWorkspaceLayout from './CallflowWorkspaceLayout.vue'
import type {
  Callflow,
  CallflowNode,
  CallflowNodePlacement,
  CallflowNodeEditorContext,
  CallflowNodeSelection,
  CallflowTreeBranchKey,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowActionCapability,
} from '../types/callRouting'

const props = withDefaults(
  defineProps<{
    record: Callflow | null
    loading: boolean
    error: string | null
    canManage: boolean
    deleting: boolean
    mutationError: string | null
    treeMoving?: boolean
    treeDeleting?: boolean
    treeMutationError?: string | null
    actionCapabilities?: Record<string, CallflowActionCapability>
  }>(),
  {
    treeMoving: false,
    treeDeleting: false,
    treeMutationError: null,
    actionCapabilities: () => ({}),
  },
)
const emit = defineEmits<{
  delete: []
  'edit-entry': []
  'add-entry': []
  'move-node': [input: CallflowTreeMoveInput]
  'reorder-nodes': [input: CallflowTreeReorderInput]
  'create-node': [context: CallflowNodeEditorContext]
  'edit-node': [context: CallflowNodeEditorContext]
  'delete-node': [path: string[]]
}>()
const confirmingDelete = ref(false)
const pendingNodeDeletion = ref<CallflowNodeSelection | null>(null)
const selectedNode = ref<CallflowNode | null>(null)
const selectedPath = ref<string[]>([])
const moveSource = ref<CallflowNodeSelection | null>(null)
const dragSource = ref<CallflowNodeSelection | null>(null)
const paletteAction = ref<CallflowAction | null>(null)
const destinationBranch = ref<CallflowTreeBranchKey | null>(null)
const nodeInfoOpen = ref(false)
const capabilityGuidedModules = computed(() =>
  Object.entries(props.actionCapabilities)
    .filter(([, capability]) => capability.enabled)
    .map(([module]) => module),
)
function actionIsGuided(action: CallflowAction | null | undefined): boolean {
  return Boolean(
    action && (action.status === 'guided' || capabilityGuidedModules.value.includes(action.module)),
  )
}
let paletteDragFrame: number | null = null
const deletionBlocker = computed(() => {
  if (props.record?.feature_code) return 'Feature-code routes cannot be deleted here.'
  if (props.record?.linked_extension) return 'This route belongs to an extension.'
  if (props.record?.phone_numbers.length) return 'Remove assigned phone numbers before deletion.'

  return null
})
const selectedAction = computed(() =>
  selectedNode.value ? findCallflowAction(selectedNode.value.module) : null,
)
const selectionBreadcrumb = computed(() => {
  const labels = ['Root']
  let node = props.record?.flow ?? null

  for (const segment of selectedPath.value) {
    node = node?.children[segment] ?? null
    if (!node) break
    labels.push(
      node.branch?.kind === 'preserved'
        ? (findCallflowAction(node.module)?.label ?? humanize(node.module))
        : (node.branch?.label ?? humanize(segment)),
    )
  }

  return labels
})
const selectedStatusLabel = computed(() => {
  if (!selectedAction.value) return 'Preserved / read only'

  return {
    guided: 'Guided now',
    planned: 'Visual editor planned',
    restricted: 'Capability required',
  }[selectedAction.value.status]
})
const selectedStatusClass = computed(() => {
  switch (selectedAction.value?.status) {
    case 'guided':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'planned':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
})
const selectedNodeTitle = computed(() =>
  selectedNode.value
    ? `${humanize(selectedNode.value.module)}${selectedNode.value.target ? `: ${selectedNode.value.target.label}` : ''}`
    : 'Callflow node',
)
const selectedMovable = computed(
  () =>
    props.canManage &&
    selectedNode.value !== null &&
    selectedPath.value.length > 0 &&
    selectedNode.value.reference_status !== 'unresolved' &&
    selectedNode.value.branch?.kind !== 'preserved' &&
    actionIsGuided(selectedAction.value),
)
const selectedNodeEditable = computed(
  () =>
    props.canManage &&
    selectedNode.value !== null &&
    (selectedPath.value.length > 0 || selectedNode.value.module === 'ring_group') &&
    selectedNode.value.reference_status !== 'unresolved' &&
    selectedNode.value.branch?.kind !== 'preserved' &&
    actionIsGuided(selectedAction.value),
)
const selectedParentAddable = computed(
  () =>
    props.canManage &&
    selectedNode.value !== null &&
    selectedNode.value.reference_status !== 'unresolved' &&
    selectedNode.value.branch?.kind !== 'preserved' &&
    actionIsGuided(selectedAction.value) &&
    canAddCallflowChild(selectedNode.value),
)
const availableBranchOptions = computed<ListboxOptionValue[]>(() => {
  if (
    !selectedNode.value ||
    selectedPath.value.some((segment) => segment.startsWith('preserved_'))
  ) {
    return []
  }

  return availableCallflowBranches(selectedNode.value)
})
const destinationEligible = computed(
  () =>
    moveSource.value !== null &&
    destinationBranch.value !== null &&
    !pathStartsWith(selectedPath.value, moveSource.value.path) &&
    !samePath([...selectedPath.value, destinationBranch.value], moveSource.value.path),
)
const reorderTargetEligible = computed(
  () =>
    moveSource.value !== null &&
    selectedNode.value !== null &&
    selectedPath.value.length > 0 &&
    !samePath(selectedPath.value, moveSource.value.path) &&
    !selectedPath.value.some((segment) => segment.startsWith('preserved_')) &&
    selectedNode.value.branch?.kind !== 'preserved' &&
    actionIsGuided(selectedAction.value),
)
const canInsertBefore = computed(
  () =>
    reorderTargetEligible.value &&
    moveSource.value !== null &&
    !pathStartsWith(selectedPath.value, moveSource.value.path) &&
    !Object.hasOwn(moveSource.value.node.children, '_'),
)
const canSwap = computed(
  () =>
    reorderTargetEligible.value &&
    moveSource.value !== null &&
    !pathStartsWith(selectedPath.value, moveSource.value.path) &&
    !pathStartsWith(moveSource.value.path, selectedPath.value),
)
const pendingNodeDeletionCount = computed(() =>
  pendingNodeDeletion.value ? countSubtreeNodes(pendingNodeDeletion.value.node) : 0,
)
const pendingNodeDeletionDescription = computed(() => {
  const count = pendingNodeDeletionCount.value

  return count > 1
    ? `This removes the selected action and all ${count - 1} action${count === 2 ? '' : 's'} below it from Switch.`
    : 'This removes the selected action from Switch.'
})

watch(
  () => props.record?.flow,
  (flow) => {
    selectedNode.value = flow ?? null
    selectedPath.value = []
    moveSource.value = null
    dragSource.value = null
    paletteAction.value = null
    destinationBranch.value = null
    nodeInfoOpen.value = false
    pendingNodeDeletion.value = null
  },
  { immediate: true },
)

watch(
  availableBranchOptions,
  (options) => {
    if (!options.some(({ value }) => value === destinationBranch.value)) {
      destinationBranch.value = (options[0]?.value as CallflowTreeBranchKey | undefined) ?? null
    }
  },
  { immediate: true },
)

function selectNode(selection: CallflowNodeSelection): void {
  selectedNode.value = selection.node
  selectedPath.value = [...selection.path]
  nodeInfoOpen.value = true
}

function samePath(left: string[], right: string[]): boolean {
  return left.length === right.length && left.every((segment, index) => segment === right[index])
}

function pathStartsWith(path: string[], prefix: string[]): boolean {
  return path.length >= prefix.length && prefix.every((segment, index) => segment === path[index])
}

function beginMove(): void {
  if (!selectedMovable.value || !selectedNode.value) return
  moveSource.value = { node: selectedNode.value, path: [...selectedPath.value] }
  nodeInfoOpen.value = false
}

function startDrag(selection: CallflowNodeSelection): void {
  paletteAction.value = null
  dragSource.value = selection
  moveSource.value = selection
}

function finishDrag(): void {
  dragSource.value = null
}

function startPaletteActionDrag(action: CallflowAction): void {
  dragSource.value = null
  if (paletteDragFrame !== null) window.cancelAnimationFrame(paletteDragFrame)
  // Wait until the browser has established its native drag session before
  // updating every canvas node with its destination state.
  paletteDragFrame = window.requestAnimationFrame(() => {
    paletteAction.value = action
    paletteDragFrame = null
  })
}

function finishPaletteActionDrag(): void {
  if (paletteDragFrame !== null) {
    window.cancelAnimationFrame(paletteDragFrame)
    paletteDragFrame = null
  }
  paletteAction.value = null
}

function requestMove(input: CallflowTreeMoveInput): void {
  if (props.treeMoving) return
  dragSource.value = null
  nodeInfoOpen.value = false
  emit('move-node', input)
}

function moveHere(): void {
  if (!moveSource.value || !destinationBranch.value || !destinationEligible.value) return
  requestMove({
    source_path: [...moveSource.value.path],
    destination_parent_path: [...selectedPath.value],
    destination_branch: destinationBranch.value,
  })
}

function requestReorder(mode: CallflowTreeReorderInput['mode']): void {
  if (!moveSource.value || props.treeMoving) return
  if (mode === 'insert_before' && !canInsertBefore.value) return
  if (mode === 'swap' && !canSwap.value) return

  emit('reorder-nodes', {
    mode,
    source_path: [...moveSource.value.path],
    target_path: [...selectedPath.value],
  })
}

onBeforeUnmount(finishPaletteActionDrag)

function cancelMove(): void {
  moveSource.value = null
  dragSource.value = null
}

function createNode(action: CallflowAction): void {
  if (!selectedNode.value) return
  const placement = callflowPalettePlacement(selectedNode.value, action)
  if (!placement) return
  createNodeAt(action, { node: selectedNode.value, path: [...selectedPath.value] }, placement)
}

function createNodeAt(
  action: CallflowAction,
  selection: CallflowNodeSelection,
  placement: CallflowNodePlacement,
): void {
  if (
    !props.canManage ||
    action.status !== 'guided' ||
    selection.node.reference_status === 'unresolved' ||
    selection.node.branch?.kind === 'preserved' ||
    !actionIsGuided(findCallflowAction(selection.node.module)) ||
    callflowPalettePlacement(selection.node, action) !== placement
  ) {
    return
  }

  selectedNode.value = selection.node
  selectedPath.value = [...selection.path]
  paletteAction.value = null
  nodeInfoOpen.value = false
  emit('create-node', {
    operation: 'create',
    path: [...selection.path],
    node: selection.node,
    module: action.module,
    placement,
    ...(action.preset ? { preset: action.preset } : {}),
  })
}

function editNode(): void {
  if (!selectedNodeEditable.value || !selectedNode.value) return
  nodeInfoOpen.value = false
  emit('edit-node', {
    operation: 'update',
    path: [...selectedPath.value],
    node: selectedNode.value,
    module: selectedNode.value.module,
  })
}

function requestNodeDeletion(selection: CallflowNodeSelection): void {
  if (!props.canManage || selection.path.length === 0 || props.treeDeleting) return
  pendingNodeDeletion.value = selection
  nodeInfoOpen.value = false
}

function confirmNodeDeletion(): void {
  if (!pendingNodeDeletion.value || props.treeDeleting) return
  emit('delete-node', [...pendingNodeDeletion.value.path])
}

function countSubtreeNodes(node: CallflowNode): number {
  return (
    1 + Object.values(node.children).reduce((total, child) => total + countSubtreeNodes(child), 0)
  )
}

function setDestinationBranch(value: ListboxValue): void {
  if (availableBranchOptions.value.some((option) => option.value === value)) {
    destinationBranch.value = value as CallflowTreeBranchKey
  }
}

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Unknown'
}
</script>

<template>
  <section aria-label="Callflow workspace" class="grid gap-5">
    <div v-if="loading" role="status" class="card-surface p-10 text-center text-xs text-slate-400">
      Loading callflow…
    </div>
    <div
      v-else-if="error"
      role="alert"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else-if="record" class="grid gap-5">
      <CallflowWorkspaceLayout
        :description="
          canManage
            ? 'Drag a guided subtree onto a node with an empty next-step branch'
            : 'Current projected Switch execution tree'
        "
      >
        <template #summary>
          <CallflowRouteSummary
            :node-count="record.node_count"
            :max-depth="record.max_depth"
            :module-count="record.modules.length"
            :status-label="humanize(record.route_type)"
            :status-detail="`Synchronized ${
              record.last_synced_at ? new Date(record.last_synced_at).toLocaleString() : 'never'
            }`"
          />
        </template>

        <CallflowDiagram
          v-if="record.flow"
          :node="record.flow"
          :entry-name="record.name"
          :numbers="record.numbers"
          :patterns="record.patterns"
          :selected-path="selectedPath"
          :editable="canManage"
          :moving="treeMoving"
          :drag-source-path="dragSource?.path ?? null"
          :palette-action="paletteAction"
          :capability-guided-modules="capabilityGuidedModules"
          @select="selectNode"
          @drag-start="startDrag"
          @drag-end="finishDrag"
          @move="requestMove"
          @add-action="(selection, action, placement) => createNodeAt(action, selection, placement)"
          @remove="requestNodeDeletion"
          @edit-entry="emit('edit-entry')"
          @add-entry="emit('add-entry')"
        >
          <template #entry-actions>
            <div v-if="canManage || mutationError" class="grid justify-items-center gap-1.5">
              <button
                v-if="canManage"
                type="button"
                :disabled="Boolean(deletionBlocker)"
                class="inline-flex h-8 items-center justify-center gap-2 rounded-md border border-red-200 bg-white px-4 text-[11px] font-semibold text-danger shadow-sm hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="confirmingDelete = true"
              >
                <TrashIcon class="size-3.5" /> Delete callflow
              </button>
              <p
                v-if="canManage && deletionBlocker"
                class="max-w-80 text-center text-[10px] text-amber-700"
              >
                {{ deletionBlocker }}
              </p>
              <p
                v-if="mutationError"
                role="alert"
                class="max-w-80 text-center text-xs font-semibold text-danger"
              >
                {{ mutationError }}
              </p>
            </div>
          </template>
        </CallflowDiagram>
        <p v-else class="text-xs text-slate-500">
          Switch did not return a structural flow for this route.
        </p>

        <template #palette="{ floating, startMoving, dock, collapse }">
          <CallflowActionPalette
            compact
            movable
            collapsible
            :floating="floating"
            :enabled="selectedParentAddable"
            :drag-enabled="canManage"
            :action-capabilities="actionCapabilities"
            @choose="createNode"
            @action-drag-start="startPaletteActionDrag"
            @action-drag-end="finishPaletteActionDrag"
            @drag-start="startMoving"
            @dock="dock"
            @collapse="collapse"
          />
        </template>

        <template #sidebar>
          <article class="card-surface p-4">
            <h2 class="text-sm font-semibold text-slate-700">Entry points</h2>
            <div class="mt-4 grid gap-3 text-xs">
              <div>
                <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Numbers</p>
                <p class="mt-1 font-mono text-slate-600">
                  {{ record.numbers.join(', ') || 'None' }}
                </p>
              </div>
              <div v-if="record.patterns.length">
                <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Patterns</p>
                <p class="mt-1 break-all font-mono text-slate-600">
                  {{ record.patterns.join(', ') }}
                </p>
              </div>
              <div v-if="record.feature_code">
                <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">
                  Feature code
                </p>
                <p class="mt-1 text-slate-600">
                  {{ record.feature_code.name ?? 'Feature code' }}
                  <span class="font-mono">{{ record.feature_code.number }}</span>
                </p>
              </div>
            </div>
          </article>

          <article class="card-surface p-4">
            <h2 class="text-sm font-semibold text-slate-700">Assignments</h2>
            <div class="mt-4 grid gap-2 text-xs text-slate-600">
              <p v-if="record.linked_extension">
                Extension:
                <span class="font-semibold text-brand-600">{{
                  record.linked_extension.display_name ?? record.linked_extension.extension
                }}</span>
              </p>
              <p v-for="number in record.phone_numbers" :key="number.id">
                Phone number:
                <span class="font-mono font-semibold text-brand-600">{{ number.number }}</span>
              </p>
              <p
                v-if="!record.linked_extension && record.phone_numbers.length === 0"
                class="text-slate-500"
              >
                No projected extension or phone-number assignment.
              </p>
            </div>
          </article>

          <div
            class="flex gap-3 rounded-md border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
          >
            <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
            <p>
              Raw node data and Switch identifiers are never exposed. Guided mutations preserve
              existing unsupported branches.
            </p>
          </div>
        </template>
      </CallflowWorkspaceLayout>
    </div>
  </section>
  <CallflowNodeInfoDialog
    v-if="selectedNode"
    :open="nodeInfoOpen"
    :title="selectedNodeTitle"
    :breadcrumb="selectionBreadcrumb.join(' / ')"
    @close="nodeInfoOpen = false"
  >
    <div class="flex flex-wrap items-center gap-3">
      <span
        class="rounded-full border px-2.5 py-1 text-[9px] font-semibold"
        :class="selectedStatusClass"
      >
        {{ selectedStatusLabel }}
      </span>
      <p class="text-[10px] text-slate-500">
        Select an action below or close to return to the canvas.
      </p>
    </div>
    <p
      v-if="selectedAction?.status === 'restricted'"
      class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] leading-4 text-amber-900"
    >
      {{ selectedAction.description }}
    </p>
    <dl class="mt-5 grid gap-4 text-[10px] sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <dt class="font-bold tracking-wide text-slate-500 uppercase">Module</dt>
        <dd class="mt-1 font-mono font-semibold text-slate-700">{{ selectedNode.module }}</dd>
      </div>
      <div>
        <dt class="font-bold tracking-wide text-slate-500 uppercase">Destination</dt>
        <dd class="mt-1 font-semibold text-slate-700">
          {{ selectedNode.target?.label ?? 'Inline Switch action' }}
        </dd>
      </div>
      <div>
        <dt class="font-bold tracking-wide text-slate-500 uppercase">Reference</dt>
        <dd class="mt-1 text-slate-700">{{ humanize(selectedNode.reference_status) }}</dd>
      </div>
      <div>
        <dt class="font-bold tracking-wide text-slate-500 uppercase">Child paths</dt>
        <dd class="mt-1 text-slate-700">{{ Object.keys(selectedNode.children).length }}</dd>
      </div>
    </dl>
    <div v-if="canManage" class="mt-5 border-t border-slate-200 pt-5">
      <button
        v-if="selectedNodeEditable && !moveSource"
        type="button"
        class="mb-3 inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:border-brand-300 hover:bg-brand-50"
        @click="editNode"
      >
        <PencilSquareIcon class="size-4" /> Edit action target
      </button>
      <div v-if="moveSource" class="grid gap-3">
        <div class="flex items-start gap-3 rounded-md border border-blue-200 bg-blue-50 p-3">
          <ArrowsRightLeftIcon class="mt-0.5 size-4 shrink-0 text-blue-600" />
          <div class="min-w-0">
            <p class="text-[10px] font-semibold text-blue-800">
              Moving {{ humanize(moveSource.node.module) }}
            </p>
            <p class="mt-0.5 text-[10px] leading-4 text-blue-700">
              Choose an empty branch for an ordinary move. Occupied-position operations are shown
              separately when safe.
            </p>
          </div>
          <button
            type="button"
            class="ml-auto text-[10px] font-semibold text-blue-700"
            :disabled="treeMoving"
            @click="cancelMove"
          >
            Cancel
          </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Empty destination branch</span>
            <FormListbox
              :model-value="destinationBranch"
              :options="availableBranchOptions"
              aria-label="Destination branch"
              :disabled="treeMoving || availableBranchOptions.length === 0"
              :placeholder="
                availableBranchOptions.length
                  ? 'Select an empty branch'
                  : 'No editable empty branch'
              "
              @update:model-value="setDestinationBranch"
            />
          </label>
          <button
            type="button"
            :disabled="!destinationEligible || treeMoving"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45"
            @click="moveHere"
          >
            <ArrowsRightLeftIcon class="size-4" />
            {{ treeMoving ? 'Moving…' : 'Move subtree here' }}
          </button>
        </div>
        <div class="grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2">
          <button
            type="button"
            :disabled="!canInsertBefore || treeMoving"
            class="rounded-md border border-slate-300 bg-white p-3 text-left disabled:cursor-not-allowed disabled:opacity-45"
            @click="requestReorder('insert_before')"
          >
            <span class="block text-xs font-semibold text-slate-700">Insert before selected</span>
            <span class="mt-1 block text-[10px] leading-4 text-slate-500">
              Put the moving action in this position and continue into the selected subtree.
            </span>
          </button>
          <button
            type="button"
            :disabled="!canSwap || treeMoving"
            class="rounded-md border border-slate-300 bg-white p-3 text-left disabled:cursor-not-allowed disabled:opacity-45"
            @click="requestReorder('swap')"
          >
            <span class="block text-xs font-semibold text-slate-700">Swap positions</span>
            <span class="mt-1 block text-[10px] leading-4 text-slate-500">
              Exchange two separate subtrees without rebuilding either action.
            </span>
          </button>
        </div>
        <p v-if="!reorderTargetEligible" class="text-[10px] leading-4 text-slate-500">
          Select another guided action on the canvas to enable compatible reorder operations.
        </p>
        <p v-else-if="!canInsertBefore && !canSwap" class="text-[10px] leading-4 text-amber-700">
          This source and target have an ancestor relationship or a continuation that makes an
          occupied-position reorder unsafe.
        </p>
      </div>
      <button
        v-else-if="selectedMovable"
        type="button"
        class="inline-flex h-9 items-center gap-2 rounded-md border border-brand-200 bg-white px-4 text-xs font-semibold text-brand-700 hover:bg-brand-50"
        @click="beginMove"
      >
        <ArrowsRightLeftIcon class="size-4" /> Move or reorder this subtree
      </button>
      <p v-else class="text-[10px] leading-4 text-slate-500">
        Root, preserved, unresolved, and not-yet-guided nodes remain read-only.
      </p>
      <p v-if="treeMutationError" role="alert" class="mt-3 text-xs font-semibold text-danger">
        {{ treeMutationError }}
      </p>
    </div>
  </CallflowNodeInfoDialog>
  <ConfirmDialog
    :open="confirmingDelete"
    title="Delete this callflow?"
    description="GridPBX will check projected dependencies again before deleting it from Switch."
    confirm-label="Delete callflow"
    :busy="deleting"
    @close="confirmingDelete = false"
    @confirm="$emit('delete')"
  />
  <ConfirmDialog
    :open="pendingNodeDeletion !== null"
    title="Remove this callflow action?"
    :description="pendingNodeDeletionDescription"
    confirm-label="Remove action"
    :busy="treeDeleting"
    @close="pendingNodeDeletion = null"
    @confirm="confirmNodeDeletion"
  />
</template>
