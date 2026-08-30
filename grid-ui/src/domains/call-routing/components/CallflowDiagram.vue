<script setup lang="ts">
import { computed } from 'vue'
import { ArrowDownIcon, LockClosedIcon } from '@heroicons/vue/24/outline'
import { callflowEntryIcon } from '../catalog/callflowActionIcons'
import type { CallflowAction } from '../catalog/callflowActionCatalog'
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
    <div class="min-h-0 flex-1 overflow-auto p-6">
      <div
        role="tree"
        aria-label="Callflow diagram"
        class="mx-auto flex w-max min-w-full flex-col items-center"
      >
        <article
          role="treeitem"
          :aria-label="`Callflow entry${entryPoints[0] ? `: ${entryPoints[0].value}` : ''}`"
          class="w-64 overflow-hidden rounded-lg border border-brand-400 bg-white shadow-sm"
        >
          <header class="flex items-center gap-2 bg-brand-500 px-3 py-2 text-white">
            <component :is="callflowEntryIcon" class="size-4" />
            <p class="text-xs font-semibold">Callflow</p>
            <span v-if="entryName" class="ml-auto max-w-28 truncate text-[9px] text-blue-100">
              {{ entryName }}
            </span>
          </header>
          <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-3 py-2.5">
            <div class="min-w-0">
              <p
                v-if="entryPoints[0]"
                class="truncate font-mono text-xs font-semibold text-slate-700"
              >
                {{ entryPoints[0].value }}
              </p>
              <p v-else class="text-[10px] font-medium text-amber-700">No assigned number</p>
              <p class="mt-0.5 text-[9px] font-medium text-slate-500">
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
        <div class="flex h-8 flex-col items-center justify-center" aria-hidden="true">
          <div class="h-4 w-px bg-slate-300"></div>
          <ArrowDownIcon class="size-3.5 text-slate-400" />
        </div>
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
