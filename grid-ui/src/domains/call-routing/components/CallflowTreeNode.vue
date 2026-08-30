<script setup lang="ts">
import { computed } from 'vue'
import { callflowActionAppearance } from '../catalog/callflowActionAppearance'
import {
  callflowNodeLabel,
  findCallflowAction,
  findCallflowActionById,
} from '../catalog/callflowActionCatalog'
import type { CallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import {
  availableCallflowBranches,
  canAddCallflowChild,
  orderedCallflowChildren,
} from '../services/callflowTreeBranches'
import type {
  CallflowNode,
  CallflowNodeSelection,
  CallflowTreeMoveInput,
} from '../types/callRouting'
import CallflowBranchConnector from './CallflowBranchConnector.vue'
import CallflowConnectorArrow from './CallflowConnectorArrow.vue'
import CallflowNodeCard from './CallflowNodeCard.vue'

defineOptions({ name: 'CallflowTreeNode' })
const props = withDefaults(
  defineProps<{
    node: CallflowNode
    depth?: number
    path?: string[]
    selectedPath?: string[]
    editable?: boolean
    moving?: boolean
    dragSourcePath?: string[] | null
    paletteAction?: CallflowAction | null
  }>(),
  {
    depth: 1,
    path: () => [],
    editable: false,
    moving: false,
    dragSourcePath: null,
    paletteAction: null,
  },
)
const emit = defineEmits<{
  select: [selection: CallflowNodeSelection]
  'drag-start': [selection: CallflowNodeSelection]
  'drag-end': []
  move: [input: CallflowTreeMoveInput]
  'add-action': [selection: CallflowNodeSelection, action: CallflowAction]
}>()
const depth = computed(() => props.depth)
const selected = computed(
  () =>
    props.selectedPath !== undefined &&
    props.selectedPath.length === props.path.length &&
    props.selectedPath.every((segment, index) => segment === props.path[index]),
)
const children = computed(() => orderedCallflowChildren(props.node))
const movable = computed(
  () =>
    props.editable &&
    !props.moving &&
    props.path.length > 0 &&
    props.node.branch?.kind !== 'preserved' &&
    findCallflowAction(props.node.module)?.status === 'guided',
)
const isDragSource = computed(() => samePath(props.path, props.dragSourcePath))
const subtreeDropAllowed = computed(
  () =>
    props.editable &&
    !props.moving &&
    props.dragSourcePath !== null &&
    props.dragSourcePath.length > 0 &&
    !Object.hasOwn(props.node.children, '_') &&
    !pathStartsWith(props.path, props.dragSourcePath) &&
    !samePath([...props.path, '_'], props.dragSourcePath),
)
const paletteDropAllowed = computed(
  () => props.paletteAction !== null && canAcceptPaletteAction(props.paletteAction),
)
const dropAllowed = computed(() => subtreeDropAllowed.value || paletteDropAllowed.value)
const inlineReferenceLabel = computed(() =>
  props.node.module === 'cidlistmatch' &&
  typeof props.node.settings?.caller_id_list_label === 'string'
    ? props.node.settings.caller_id_list_label
    : null,
)
const nodeDetail = computed(() => {
  if (props.node.target) return props.node.target.label
  if (inlineReferenceLabel.value) return inlineReferenceLabel.value
  if (
    props.node.module === 'manual_presence' &&
    typeof props.node.settings?.presence_id === 'string'
  ) {
    return props.node.settings.presence_id
  }
  if (props.node.reference_status === 'unresolved') return 'Target is not projected'

  return 'Inline Switch action'
})
const showBranchLabel = computed(() => {
  const branch = props.node.branch
  if (!branch) return false

  return branch.kind !== 'default' || branch.label.trim().toLowerCase() !== 'default branch'
})

function selectNode(): void {
  emit('select', { node: props.node, path: [...props.path] })
}

function childPath(childKey: string): string[] {
  return [...props.path, childKey]
}

function forwardSelection(selection: CallflowNodeSelection): void {
  emit('select', selection)
}

function forwardAddAction(selection: CallflowNodeSelection, action: CallflowAction): void {
  emit('add-action', selection, action)
}

function samePath(left: string[], right?: string[] | null): boolean {
  return (
    right !== undefined &&
    right !== null &&
    left.length === right.length &&
    left.every((segment, index) => segment === right[index])
  )
}

function pathStartsWith(path: string[], prefix: string[]): boolean {
  return path.length >= prefix.length && prefix.every((segment, index) => segment === path[index])
}

function startDragging(event: DragEvent): void {
  if (!movable.value) {
    event.preventDefault()
    return
  }

  event.dataTransfer?.setData('text/plain', JSON.stringify(props.path))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
  emit('drag-start', { node: props.node, path: [...props.path] })
}

function allowDrop(event: DragEvent): void {
  const paletteAction = paletteActionFromEvent(event)
  const acceptsPaletteTransfer =
    canAcceptPaletteTarget() &&
    (props.paletteAction !== null || paletteAction !== null || hasPaletteTransfer(event))
  if (!subtreeDropAllowed.value && !acceptsPaletteTransfer) return
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = acceptsPaletteTransfer ? 'copy' : 'move'
  }
}

function dropNode(event: DragEvent): void {
  const droppedPaletteAction = props.paletteAction ?? paletteActionFromEvent(event)
  if (!dropAllowed.value && !canAcceptPaletteAction(droppedPaletteAction)) return
  event.preventDefault()

  if (canAcceptPaletteAction(droppedPaletteAction) && droppedPaletteAction) {
    emit('add-action', { node: props.node, path: [...props.path] }, droppedPaletteAction)
    return
  }

  if (!subtreeDropAllowed.value || props.dragSourcePath === null) return
  emit('move', {
    source_path: [...props.dragSourcePath],
    destination_parent_path: [...props.path],
    destination_branch: '_',
  })
}

function paletteActionFromEvent(event: DragEvent): CallflowAction | null {
  const getData = event.dataTransfer?.getData
  if (typeof getData !== 'function') return null

  const actionId = getData.call(event.dataTransfer, 'application/x-gridpbx-callflow-action')
  return actionId ? findCallflowActionById(actionId) : null
}

function hasPaletteTransfer(event: DragEvent): boolean {
  return Array.from(event.dataTransfer?.types ?? []).includes(
    'application/x-gridpbx-callflow-action',
  )
}

function canAcceptPaletteTarget(): boolean {
  return (
    props.editable &&
    !props.moving &&
    props.node.reference_status !== 'unresolved' &&
    props.node.branch?.kind !== 'preserved' &&
    findCallflowAction(props.node.module)?.status === 'guided' &&
    canAddCallflowChild(props.node)
  )
}

function canAcceptPaletteAction(action: CallflowAction | null): boolean {
  return action?.status === 'guided' && canAcceptPaletteTarget()
}

const moduleIcon = computed(() =>
  callflowActionIcon(props.node.module, props.node.reference_status === 'unresolved'),
)
const moduleLabel = computed(() => callflowNodeLabel(props.node))
const appearance = computed(() =>
  callflowActionAppearance(props.node.module, props.node.reference_status === 'unresolved'),
)
const branchClass = computed(() => {
  switch (props.node.branch?.kind) {
    case 'schedule_match':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'condition':
      return 'border-violet-200 bg-violet-50 text-violet-700'
    case 'key':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'preserved':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    default:
      return 'border-slate-300 bg-white text-slate-600'
  }
})
</script>

<template>
  <div class="flex min-w-36 flex-col items-center">
    <div v-if="node.branch" class="mb-2 flex flex-col items-center gap-1">
      <CallflowConnectorArrow />
      <span
        v-if="showBranchLabel"
        class="rounded-full border px-2 py-0.5 text-[9px] font-semibold"
        :class="branchClass"
      >
        {{ node.branch.label }}
      </span>
    </div>
    <button
      type="button"
      role="treeitem"
      :aria-level="depth"
      :aria-selected="selected"
      :aria-disabled="moving || undefined"
      :aria-label="`${moduleLabel}${node.target ? `: ${node.target.label}` : ''}`"
      :draggable="movable"
      :title="movable ? 'Drag this subtree to an empty branch' : undefined"
      class="h-[84px] w-36 rounded-md text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      :class="[
        dropAllowed
          ? 'ring-2 ring-emerald-400 ring-offset-2'
          : selected
            ? 'ring-2 ring-brand-500 ring-offset-2'
            : '',
        isDragSource && 'opacity-55',
        movable && 'cursor-grab active:cursor-grabbing',
      ]"
      @click="selectNode"
      @dragstart="startDragging"
      @dragend="emit('drag-end')"
      @dragover="allowDrop"
      @drop="dropNode"
    >
      <CallflowNodeCard
        :label="moduleLabel"
        :module="node.module"
        :icon="moduleIcon"
        :border-class="appearance.nodeBorder"
        :icon-class="appearance.nodeIcon"
        :detail="nodeDetail"
        :movable="movable"
      />
    </button>

    <div v-if="children.length" role="group" class="flex flex-col items-center">
      <CallflowBranchConnector v-if="children.length > 1" kind="parent-stem" />
      <div class="flex items-start">
        <div
          v-for="([childKey, child], childIndex) in children"
          :key="childKey"
          class="relative flex items-start"
          :class="children.length > 1 && 'px-3'"
        >
          <CallflowBranchConnector
            v-if="children.length > 1"
            :kind="
              childIndex === 0 ? 'first' : childIndex === children.length - 1 ? 'last' : 'middle'
            "
          />
          <CallflowTreeNode
            :node="child"
            :depth="depth + 1"
            :path="childPath(childKey)"
            :selected-path="selectedPath"
            :editable="editable"
            :moving="moving"
            :drag-source-path="dragSourcePath"
            :palette-action="paletteAction"
            @select="forwardSelection"
            @drag-start="emit('drag-start', $event)"
            @drag-end="emit('drag-end')"
            @move="emit('move', $event)"
            @add-action="forwardAddAction"
          />
        </div>
      </div>
    </div>
  </div>
</template>
