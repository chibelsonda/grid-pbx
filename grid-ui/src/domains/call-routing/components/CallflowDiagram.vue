<script setup lang="ts">
import { computed, ref } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
import { callflowEntryIcon } from '../catalog/callflowActionIcons'
import { useDragToPan } from '../composables/useDragToPan'
import type { CallflowAction } from '../catalog/callflowActionCatalog'
import CallflowConnectorArrow from './CallflowConnectorArrow.vue'
import CallflowTreeNode from './CallflowTreeNode.vue'
import type {
  CallflowNode,
  CallflowNodeSelection,
  CallflowTreeMoveInput,
} from '../types/callRouting'

const props = withDefaults(
  defineProps<{
    node: CallflowNode
    entryName?: string | null
    numbers?: string[]
    patterns?: string[]
    selectedPath?: string[]
    editable?: boolean
    moving?: boolean
    dragSourcePath?: string[] | null
    paletteAction?: CallflowAction | null
  }>(),
  { entryName: null, numbers: () => [], patterns: () => [], paletteAction: null },
)
defineEmits<{
  select: [selection: CallflowNodeSelection]
  'drag-start': [selection: CallflowNodeSelection]
  'drag-end': []
  move: [input: CallflowTreeMoveInput]
  'add-action': [selection: CallflowNodeSelection, action: CallflowAction]
}>()
const entryPoints = computed(() => [
  ...props.numbers.map((value) => ({ value, kind: 'Number' })),
  ...props.patterns.map((value) => ({ value, kind: 'Pattern' })),
])
const panCanvas = ref<HTMLElement | null>(null)
const { isPanning, startPanning, pan, stopPanning } = useDragToPan(panCanvas)
</script>

<template>
  <div
    class="flex h-[calc(100dvh-7rem)] min-h-[36rem] flex-col overflow-hidden rounded-lg border border-slate-200 bg-slate-50/70"
  >
    <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
      <div>
        <h3 class="text-xs font-semibold text-slate-700">Visual route map</h3>
        <p class="mt-0.5 text-[10px] text-slate-500">
          {{
            editable
              ? 'Drag a guided subtree onto a node with an empty next-step branch'
              : 'Current projected Switch execution tree'
          }}
        </p>
      </div>
      <div class="ml-auto flex flex-wrap items-center gap-2 text-[9px] font-semibold">
        <span
          class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-700"
        >
          Schedule match
        </span>
        <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-blue-700">
          Menu key
        </span>
        <span class="rounded-full border border-slate-300 bg-white px-2 py-1 text-slate-600">
          Default
        </span>
        <span
          class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700"
        >
          <LockClosedIcon class="size-3" /> Preserved
        </span>
      </div>
    </header>
    <div
      ref="panCanvas"
      data-callflow-pan-canvas
      class="min-h-0 flex-1 overflow-auto p-4 select-none"
      :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'"
      @pointerdown="startPanning"
      @pointermove="pan"
      @pointerup="stopPanning"
      @pointercancel="stopPanning"
      @lostpointercapture="stopPanning"
    >
      <div
        role="tree"
        aria-label="Callflow diagram"
        class="mx-auto flex w-max min-w-full flex-col items-center"
      >
        <article
          role="treeitem"
          :aria-label="`Callflow entry${entryPoints[0] ? `: ${entryPoints[0].value}` : ''}`"
          class="h-14 w-80 overflow-hidden rounded-md border border-brand-500 bg-white shadow-sm"
        >
          <header class="flex h-6 items-center gap-2 bg-brand-600 px-2 text-white">
            <component :is="callflowEntryIcon" class="size-3.5" />
            <p class="text-[10px] font-semibold">Callflow</p>
            <span v-if="entryName" class="ml-auto max-w-28 truncate text-[9px] text-blue-100">
              {{ entryName }}
            </span>
          </header>
          <div class="grid h-8 grid-cols-[minmax(0,1fr)_auto] items-center gap-2 px-2">
            <div class="min-w-0">
              <p
                v-if="entryPoints[0]"
                class="truncate font-mono text-[10px] font-semibold text-slate-700"
              >
                {{ entryPoints[0].value }}
              </p>
              <p v-else class="text-[10px] font-medium text-amber-700">No assigned number</p>
              <p class="text-[8px] font-medium text-slate-500">
                {{ entryPoints[0]?.kind ?? 'Unassigned route' }}
              </p>
            </div>
            <span
              v-if="entryPoints.length > 1"
              class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[9px] font-semibold text-slate-600"
            >
              +{{ entryPoints.length - 1 }} more
            </span>
          </div>
        </article>
        <CallflowConnectorArrow />
        <CallflowTreeNode
          :node="node"
          :selected-path="selectedPath"
          :editable="editable"
          :moving="moving"
          :drag-source-path="dragSourcePath"
          :palette-action="paletteAction"
          @select="$emit('select', $event)"
          @drag-start="$emit('drag-start', $event)"
          @drag-end="$emit('drag-end')"
          @move="$emit('move', $event)"
          @add-action="(selection, action) => $emit('add-action', selection, action)"
        />
      </div>
    </div>
  </div>
</template>
