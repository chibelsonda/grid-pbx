<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  ArrowLeftIcon,
  ArrowPathRoundedSquareIcon,
  ArrowsRightLeftIcon,
  HashtagIcon,
  LinkIcon,
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
  canAddCallflowChild,
} from '../services/callflowTreeBranches'
import CallflowActionPalette from './CallflowActionPalette.vue'
import CallflowDiagram from './CallflowDiagram.vue'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'
import type {
  Callflow,
  CallflowNode,
  CallflowNodeEditorContext,
  CallflowNodeSelection,
  CallflowTreeBranchKey,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
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
    treeMutationError?: string | null
  }>(),
  { treeMoving: false, treeMutationError: null },
)
const emit = defineEmits<{
  close: []
  edit: []
  delete: []
  'move-node': [input: CallflowTreeMoveInput]
  'reorder-nodes': [input: CallflowTreeReorderInput]
  'create-node': [context: CallflowNodeEditorContext]
  'edit-node': [context: CallflowNodeEditorContext]
}>()
const confirmingDelete = ref(false)
const selectedNode = ref<CallflowNode | null>(null)
const selectedPath = ref<string[]>([])
const moveSource = ref<CallflowNodeSelection | null>(null)
const dragSource = ref<CallflowNodeSelection | null>(null)
const paletteAction = ref<CallflowAction | null>(null)
const destinationBranch = ref<CallflowTreeBranchKey | null>(null)
const nodeInfoOpen = ref(false)
const paletteShell = ref<HTMLElement | null>(null)
const paletteFloating = ref(false)
const palettePosition = ref({ left: 0, top: 0, width: 304 })
let palettePointerOffset = { x: 0, y: 0 }
const title = computed(
  () =>
    props.record?.name ??
    props.record?.feature_code?.name ??
    props.record?.numbers[0] ??
    'Call route details',
)
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
    labels.push(node.branch?.label ?? humanize(segment))
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
    selectedAction.value?.status === 'guided',
)
const selectedNodeEditable = computed(
  () =>
    props.canManage &&
    selectedNode.value !== null &&
    selectedPath.value.length > 0 &&
    selectedNode.value.branch?.kind !== 'preserved' &&
    selectedAction.value?.status === 'guided',
)
const selectedParentAddable = computed(
  () =>
    props.canManage &&
    selectedNode.value !== null &&
    selectedNode.value.reference_status !== 'unresolved' &&
    selectedNode.value.branch?.kind !== 'preserved' &&
    selectedAction.value?.status === 'guided' &&
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
    selectedAction.value?.status === 'guided',
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
  // The action travels with the native DataTransfer payload. Avoid mutating the
  // route tree during dragstart because replacing the drag source can cancel the
  // browser's active drag operation.
  void action
}

function finishPaletteActionDrag(): void {
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

function movePalette(event: PointerEvent): void {
  if (!paletteFloating.value) return
  const margin = 8
  const maxLeft = Math.max(margin, window.innerWidth - palettePosition.value.width - margin)
  const maxTop = Math.max(margin, window.innerHeight - 120)

  palettePosition.value = {
    ...palettePosition.value,
    left: Math.min(maxLeft, Math.max(margin, event.clientX - palettePointerOffset.x)),
    top: Math.min(maxTop, Math.max(margin, event.clientY - palettePointerOffset.y)),
  }
}

function stopMovingPalette(): void {
  window.removeEventListener('pointermove', movePalette)
  window.removeEventListener('pointerup', stopMovingPalette)
  window.removeEventListener('pointercancel', stopMovingPalette)
}

function startMovingPalette(event: PointerEvent): void {
  if (event.button !== 0 || !paletteShell.value) return
  const bounds = paletteShell.value.getBoundingClientRect()
  palettePosition.value = { left: bounds.left, top: bounds.top, width: bounds.width }
  palettePointerOffset = { x: event.clientX - bounds.left, y: event.clientY - bounds.top }
  paletteFloating.value = true
  window.addEventListener('pointermove', movePalette)
  window.addEventListener('pointerup', stopMovingPalette)
  window.addEventListener('pointercancel', stopMovingPalette)
  event.preventDefault()
}

function dockPalette(): void {
  stopMovingPalette()
  paletteFloating.value = false
}

const paletteStyle = computed(() =>
  paletteFloating.value
    ? {
        left: `${palettePosition.value.left}px`,
        top: `${palettePosition.value.top}px`,
        width: `${palettePosition.value.width}px`,
        maxHeight: 'calc(100vh - 16px)',
      }
    : undefined,
)

onBeforeUnmount(stopMovingPalette)

function cancelMove(): void {
  moveSource.value = null
  dragSource.value = null
}

function createNode(action: CallflowAction): void {
  if (!selectedParentAddable.value || !selectedNode.value) return
  createNodeAt(action, { node: selectedNode.value, path: [...selectedPath.value] })
}

function createNodeAt(action: CallflowAction, selection: CallflowNodeSelection): void {
  if (
    !props.canManage ||
    action.status !== 'guided' ||
    selection.node.reference_status === 'unresolved' ||
    selection.node.branch?.kind === 'preserved' ||
    findCallflowAction(selection.node.module)?.status !== 'guided' ||
    !canAddCallflowChild(selection.node)
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
    <header class="card-surface flex flex-wrap items-center gap-4 p-5">
      <button
        type="button"
        aria-label="Back to call routes"
        class="grid size-9 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50"
        @click="$emit('close')"
      >
        <ArrowLeftIcon class="size-4" />
      </button>
      <div class="min-w-0">
        <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
          Callflow workspace
        </p>
        <h2 class="mt-1 truncate text-lg font-semibold text-slate-800">{{ title }}</h2>
        <p class="mt-1 text-xs text-slate-500">
          The full-width route map stays on the main page; select a node to inspect it in a modal.
        </p>
      </div>
      <button
        v-if="canManage && record"
        type="button"
        class="ml-auto inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
        @click="$emit('edit')"
      >
        <PencilSquareIcon class="size-4" /> Edit guided route
      </button>
    </header>

    <div v-if="loading" class="card-surface p-10 text-center text-xs text-slate-400">
      Loading call route…
    </div>
    <div
      v-else-if="error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else-if="record" class="grid gap-5">
      <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_11.5rem] xl:items-start">
        <article class="card-surface min-w-0 p-4 sm:p-5">
          <header class="mb-4 flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3">
            <h2 class="mr-auto text-sm font-semibold text-slate-700">Route structure</h2>
            <div class="flex flex-wrap items-center gap-2" aria-label="Route summary">
              <div
                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5"
              >
                <HashtagIcon class="size-3.5 text-blue-600" />
                <span class="text-xs font-semibold text-slate-700">{{ record.node_count }}</span>
                <span class="text-[9px] font-bold tracking-wide text-slate-500 uppercase"
                  >Nodes</span
                >
              </div>
              <div
                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5"
              >
                <LinkIcon class="size-3.5 text-violet-600" />
                <span class="text-xs font-semibold text-slate-700">{{ record.max_depth }}</span>
                <span class="text-[9px] font-bold tracking-wide text-slate-500 uppercase">
                  Max depth
                </span>
              </div>
              <div
                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5"
              >
                <ArrowPathRoundedSquareIcon class="size-3.5 text-emerald-600" />
                <span class="text-xs font-semibold text-slate-700">{{
                  record.modules.length
                }}</span>
                <span class="text-[9px] font-bold tracking-wide text-slate-500 uppercase">
                  Modules
                </span>
              </div>
              <div
                class="inline-flex min-h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1"
              >
                <ArrowPathRoundedSquareIcon class="size-3.5 shrink-0 text-brand-600" />
                <span class="text-[10px] font-semibold text-slate-700">
                  {{ humanize(record.route_type) }}
                </span>
                <span class="text-[9px] text-slate-500">
                  Synchronized
                  {{
                    record.last_synced_at
                      ? new Date(record.last_synced_at).toLocaleString()
                      : 'never'
                  }}
                </span>
              </div>
            </div>
          </header>
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
            @select="selectNode"
            @drag-start="startDrag"
            @drag-end="finishDrag"
            @move="requestMove"
            @add-action="(selection, action) => createNodeAt(action, selection)"
          />
          <p v-else class="text-xs text-slate-500">
            Switch did not return a structural flow for this route.
          </p>
        </article>

        <aside class="grid gap-4">
          <div
            ref="paletteShell"
            :style="paletteStyle"
            :class="
              paletteFloating
                ? 'fixed z-40 overflow-hidden rounded-lg shadow-2xl ring-1 ring-slate-300'
                : 'xl:sticky xl:top-3'
            "
          >
            <CallflowActionPalette
              compact
              movable
              :floating="paletteFloating"
              :enabled="selectedParentAddable"
              :drag-enabled="canManage"
              @choose="createNode"
              @action-drag-start="startPaletteActionDrag"
              @action-drag-end="finishPaletteActionDrag"
              @drag-start="startMovingPalette"
              @dock="dockPalette"
            />
          </div>
          <article class="card-surface p-5">
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

          <article class="card-surface p-5">
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

          <button
            v-if="canManage"
            type="button"
            :disabled="Boolean(deletionBlocker)"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-200 bg-white px-5 text-xs font-semibold text-danger hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
            @click="confirmingDelete = true"
          >
            <TrashIcon class="size-4" /> Delete route
          </button>
          <p v-if="canManage && deletionBlocker" class="text-[10px] text-amber-700">
            {{ deletionBlocker }}
          </p>
          <p v-if="mutationError" class="text-xs font-semibold text-danger">
            {{ mutationError }}
          </p>
        </aside>
      </div>
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
      <p v-if="treeMutationError" class="mt-3 text-xs font-semibold text-danger">
        {{ treeMutationError }}
      </p>
    </div>
  </CallflowNodeInfoDialog>
  <ConfirmDialog
    :open="confirmingDelete"
    title="Delete this route?"
    description="GridPBX will check projected dependencies again before deleting it from Switch."
    confirm-label="Delete route"
    :busy="deleting"
    @close="confirmingDelete = false"
    @confirm="$emit('delete')"
  />
</template>
