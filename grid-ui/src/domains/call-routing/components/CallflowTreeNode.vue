<script setup lang="ts">
import { computed } from 'vue'
import { ArrowDownIcon, ArrowsPointingOutIcon } from '@heroicons/vue/24/outline'
import { findCallflowAction } from '../catalog/callflowActionCatalog'
import type { CallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import { availableCallflowBranches } from '../services/callflowTreeBranches'
import type {
  CallflowNode,
  CallflowNodeSelection,
  CallflowTreeMoveInput,
} from '../types/callRouting'

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
const children = computed(() => Object.entries(props.node.children))
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

  const module = getData.call(event.dataTransfer, 'application/x-gridpbx-callflow-action')
  return module ? findCallflowAction(module) : null
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
    availableCallflowBranches(props.node).length > 0
  )
}

function canAcceptPaletteAction(action: CallflowAction | null): boolean {
  return action?.status === 'guided' && canAcceptPaletteTarget()
}

const moduleIcon = computed(() =>
  callflowActionIcon(props.node.module, props.node.reference_status === 'unresolved'),
)
const moduleLabel = computed(
  () => findCallflowAction(props.node.module)?.label ?? humanize(props.node.module),
)
const branchClass = computed(() => {
  switch (props.node.branch?.kind) {
    case 'schedule_match':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'key':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'preserved':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    default:
      return 'border-slate-300 bg-white text-slate-600'
  }
})

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}
</script>

<template>
  <div class="flex min-w-52 flex-col items-center">
    <div v-if="node.branch" class="mb-2 flex flex-col items-center gap-1">
      <ArrowDownIcon class="size-3 text-slate-400" />
      <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold" :class="branchClass">
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
      class="w-52 rounded-lg border bg-white text-left shadow-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      :class="[
        dropAllowed
          ? 'border-emerald-500 ring-2 ring-emerald-100'
          : selected
            ? 'border-brand-500 ring-2 ring-brand-100'
            : node.reference_status === 'unresolved'
              ? 'border-amber-300'
              : 'border-slate-300 hover:border-brand-300',
        isDragSource && 'opacity-55',
        movable && 'cursor-grab active:cursor-grabbing',
      ]"
      @click="selectNode"
      @dragstart="startDragging"
      @dragend="emit('drag-end')"
      @dragover="allowDrop"
      @drop="dropNode"
    >
      <header class="flex items-center gap-3 border-b border-slate-200 px-3 py-2.5">
        <span
          class="grid size-8 shrink-0 place-items-center rounded-md"
          :class="
            node.reference_status === 'unresolved'
              ? 'bg-amber-50 text-amber-600'
              : 'bg-brand-50 text-brand-600'
          "
        >
          <component :is="moduleIcon" class="size-4" />
        </span>
        <div class="min-w-0">
          <p class="truncate text-xs font-semibold text-slate-800">{{ moduleLabel }}</p>
          <p class="mt-0.5 font-mono text-[9px] text-slate-500">{{ node.module }}</p>
        </div>
        <span class="ml-auto text-[9px] font-bold text-slate-400">{{ depth }}</span>
        <ArrowsPointingOutIcon v-if="movable" class="size-3.5 text-slate-400" />
      </header>
      <div class="min-h-12 px-3 py-2.5">
        <p v-if="node.target" class="truncate text-[10px] font-semibold text-brand-700">
          {{ node.target.label }}
        </p>
        <p
          v-else-if="node.reference_status === 'unresolved'"
          class="text-[10px] font-semibold leading-4 text-amber-700"
        >
          Target is not projected
        </p>
        <p v-else class="text-[10px] leading-4 text-slate-500">Inline Switch action</p>
        <p v-if="dropAllowed" class="mt-2 text-[9px] font-semibold text-emerald-700">
          {{ paletteDropAllowed ? 'Drop to configure this action' : 'Drop here as the next step' }}
        </p>
      </div>
    </button>

    <div v-if="children.length" role="group" class="flex flex-col items-center">
      <div class="h-5 w-px bg-slate-300"></div>
      <div class="flex items-center gap-3">
        <div v-if="children.length > 1" class="h-px w-8 bg-slate-300"></div>
        <span
          class="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[9px] font-semibold text-slate-500"
        >
          {{ children.length }} {{ children.length === 1 ? 'path' : 'paths' }}
        </span>
        <div v-if="children.length > 1" class="h-px w-8 bg-slate-300"></div>
      </div>
      <div class="flex items-start gap-5 pt-2">
        <CallflowTreeNode
          v-for="[childKey, child] in children"
          :key="childKey"
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
</template>
