<script setup lang="ts">
import { computed } from 'vue'
import { XMarkIcon } from '@heroicons/vue/20/solid'
import { callflowActionAppearance } from '../catalog/callflowActionAppearance'
import {
  callflowNodeLabel,
  findCallflowAction,
  findCallflowActionById,
} from '../catalog/callflowActionCatalog'
import type { CallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import {
  callflowNodeDropDecision,
  callflowPalettePlacement,
  orderedCallflowChildren,
} from '../services/callflowTreeBranches'
import type {
  CallflowNode,
  CallflowNodePlacement,
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
    capabilityGuidedModules?: string[]
  }>(),
  {
    depth: 1,
    path: () => [],
    editable: false,
    moving: false,
    dragSourcePath: null,
    paletteAction: null,
    capabilityGuidedModules: () => [],
  },
)
const emit = defineEmits<{
  select: [selection: CallflowNodeSelection]
  'drag-start': [selection: CallflowNodeSelection]
  'drag-end': []
  move: [input: CallflowTreeMoveInput]
  'add-action': [
    selection: CallflowNodeSelection,
    action: CallflowAction,
    placement: CallflowNodePlacement,
  ]
  remove: [selection: CallflowNodeSelection]
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
    (findCallflowAction(props.node.module)?.status === 'guided' ||
      props.capabilityGuidedModules.includes(props.node.module)),
)
const removable = computed(
  () =>
    props.editable &&
    !props.moving &&
    props.path.length > 0 &&
    props.node.branch?.kind !== 'preserved' &&
    (findCallflowAction(props.node.module)?.status === 'guided' ||
      props.capabilityGuidedModules.includes(props.node.module)),
)
const isDragSource = computed(() => samePath(props.path, props.dragSourcePath))
const dropDecision = computed(() => decisionFor(props.paletteAction))
const dropAllowed = computed(() => dropDecision.value.state === 'allowed')
const dropDisallowed = computed(() => dropDecision.value.state === 'disallowed')
const dropTitle = computed(() => {
  if (dropDisallowed.value) return dropDecision.value.reason ?? 'Drop not allowed'
  if (dropAllowed.value) {
    return dropDecision.value.effect === 'copy'
      ? 'Drop to configure this action here'
      : 'Drop to move this subtree here'
  }

  return movable.value ? 'Drag this subtree to an empty branch' : undefined
})
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
  if (branch.kind === 'preserved') return false

  return branch.kind !== 'default' || branch.label.trim().toLowerCase() !== 'default branch'
})
const branchDisplayLabel = computed(() => {
  const branch = props.node.branch
  if (!branch) return ''

  // Monster presents menu keys and timeout as the raw branch value in a
  // compact header spanning the child node. Retain descriptive labels for
  // conditional and schedule branches where the raw value is less useful.
  return branch.kind === 'key' ? branch.key : branch.label
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

function forwardAddAction(
  selection: CallflowNodeSelection,
  action: CallflowAction,
  placement: CallflowNodePlacement,
): void {
  emit('add-action', selection, action, placement)
}

function samePath(left: string[], right?: string[] | null): boolean {
  return (
    right !== undefined &&
    right !== null &&
    left.length === right.length &&
    left.every((segment, index) => segment === right[index])
  )
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
  const acceptsPaletteTransfer = canAcceptPaletteAction(paletteAction ?? props.paletteAction)
  const acceptsUnresolvedPaletteTransfer =
    hasPaletteTransfer(event) && decisionForGenericPaletteAction().state === 'allowed'
  const decision =
    acceptsPaletteTransfer || acceptsUnresolvedPaletteTransfer
      ? { state: 'allowed' as const, effect: 'copy' as const }
      : dropDecision.value

  if (decision.state !== 'allowed') {
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'none'
    return
  }

  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = decision.effect ?? 'none'
  }
}

function dropNode(event: DragEvent): void {
  const droppedPaletteAction = props.paletteAction ?? paletteActionFromEvent(event)
  const decision = droppedPaletteAction ? decisionFor(droppedPaletteAction) : dropDecision.value
  if (decision.state !== 'allowed') return
  event.preventDefault()

  if (canAcceptPaletteAction(droppedPaletteAction) && droppedPaletteAction) {
    const placement = callflowPalettePlacement(props.node, droppedPaletteAction)
    if (!placement) return
    emit('add-action', { node: props.node, path: [...props.path] }, droppedPaletteAction, placement)
    return
  }

  if (props.dragSourcePath === null) return
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

function decisionFor(action: CallflowAction | null) {
  return callflowNodeDropDecision({
    node: props.node,
    path: props.path,
    editable: props.editable,
    moving: props.moving,
    dragSourcePath: props.dragSourcePath,
    paletteAction: action,
  })
}

function decisionForGenericPaletteAction() {
  return decisionFor({
    id: 'dragged-guided-action',
    module: 'unknown',
    label: 'Dragged action',
    description: '',
    status: 'guided',
  })
}

function canAcceptPaletteAction(action: CallflowAction | null): boolean {
  return action !== null && decisionFor(action).state === 'allowed'
}

const moduleIcon = computed(() =>
  callflowActionIcon(props.node.module, {
    action:
      typeof props.node.settings?.action === 'string' ? props.node.settings.action : undefined,
    unresolved: props.node.reference_status === 'unresolved',
  }),
)
const moduleLabel = computed(() => callflowNodeLabel(props.node))
const appearance = computed(() =>
  callflowActionAppearance(props.node.module, props.node.reference_status === 'unresolved'),
)
const branchClass = computed(() => {
  switch (props.node.branch?.kind) {
    case 'schedule_match':
      return 'border-emerald-400/40 bg-callflow-node text-emerald-200'
    case 'condition':
      return 'border-violet-400/40 bg-callflow-node text-violet-200'
    case 'key':
      return 'border-slate-400/40 bg-callflow-node text-slate-100'
    case 'preserved':
      return 'border-amber-400/40 bg-callflow-node text-amber-200'
    default:
      return 'border-slate-400/40 bg-callflow-node text-slate-100'
  }
})
</script>

<template>
  <div class="relative flex min-w-36 flex-col items-center">
    <div v-if="node.branch" class="mb-1 flex flex-col items-center">
      <CallflowConnectorArrow />
      <span
        v-if="showBranchLabel"
        data-callflow-branch-label
        class="flex h-4 w-36 items-center justify-center rounded-[2px] border px-1 text-center text-[9px] leading-none font-medium"
        :class="branchClass"
      >
        {{ branchDisplayLabel }}
      </span>
    </div>
    <button
      type="button"
      role="treeitem"
      :aria-level="depth"
      :aria-selected="selected"
      :aria-disabled="moving || undefined"
      :aria-label="`${moduleLabel}${node.target ? `: ${node.target.label}` : ''}`"
      :aria-description="
        dropAllowed
          ? 'Drop allowed'
          : dropDisallowed
            ? `Drop not allowed. ${dropDecision.reason ?? ''}`
            : undefined
      "
      :draggable="movable"
      :title="dropTitle"
      :data-drop-state="dropDecision.state"
      class="relative h-[84px] w-36 rounded-md text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      :class="[
        dropAllowed
          ? dropDecision.effect === 'copy'
            ? 'cursor-copy ring-2 ring-emerald-400 ring-offset-2'
            : 'cursor-grabbing ring-2 ring-emerald-400 ring-offset-2'
          : dropDisallowed
            ? 'cursor-not-allowed opacity-45 ring-1 ring-rose-300 ring-offset-1 grayscale-[20%]'
            : selected
              ? 'ring-2 ring-brand-500 ring-offset-2'
              : '',
        isDragSource && !dropDisallowed && 'opacity-55',
        movable && dropDecision.state === 'idle' && 'cursor-grab active:cursor-grabbing',
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
    <button
      v-if="removable"
      type="button"
      :aria-label="`Remove ${moduleLabel}`"
      :title="`Remove ${moduleLabel}`"
      class="absolute top-1 right-1 z-10 grid size-4 place-items-center rounded-sm text-white/75 transition hover:bg-black/30 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-white"
      @click.stop="emit('remove', { node, path: [...path] })"
    >
      <XMarkIcon class="size-3.5" />
    </button>

    <div v-if="children.length" role="group" class="mt-1 flex flex-col items-center">
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
            :capability-guided-modules="capabilityGuidedModules"
            @select="forwardSelection"
            @drag-start="emit('drag-start', $event)"
            @drag-end="emit('drag-end')"
            @move="emit('move', $event)"
            @add-action="forwardAddAction"
            @remove="emit('remove', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
