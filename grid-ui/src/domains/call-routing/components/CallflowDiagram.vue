<script setup lang="ts">
import { computed, ref } from 'vue'
import { MagnifyingGlassMinusIcon, MagnifyingGlassPlusIcon } from '@heroicons/vue/24/outline'
import { useCanvasZoom } from '../composables/useCanvasZoom'
import { useDragToPan } from '../composables/useDragToPan'
import type { CallflowAction } from '../catalog/callflowActionCatalog'
import CallflowConnectorArrow from './CallflowConnectorArrow.vue'
import CallflowCanvasHeader from './CallflowCanvasHeader.vue'
import CallflowEntryNode from './CallflowEntryNode.vue'
import CallflowTreeNode from './CallflowTreeNode.vue'
import type {
  CallflowNode,
  CallflowNodePlacement,
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
    capabilityGuidedModules?: string[]
  }>(),
  {
    entryName: null,
    numbers: () => [],
    patterns: () => [],
    paletteAction: null,
    capabilityGuidedModules: () => [],
  },
)
defineSlots<{
  'entry-actions'(): unknown
}>()
defineEmits<{
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
  'edit-entry': []
  'add-entry': []
}>()
const entryPoints = computed(() => [
  ...props.numbers.map((value) => ({ value, kind: 'Number' })),
  ...props.patterns.map((value) => ({ value, kind: 'Pattern' })),
])
const panCanvas = ref<HTMLElement | null>(null)
const { isPanning, startPanning, pan, stopPanning } = useDragToPan(panCanvas)
const { zoom, zoomPercent, canZoomIn, canZoomOut, zoomIn, zoomOut, resetZoom, handleZoomWheel } =
  useCanvasZoom()
const canvasTextureStyle = computed<Record<string, string>>(() => {
  const scale = (pixels: number) => `${Number((pixels * zoom.value).toFixed(2))}px`

  return {
    '--callflow-grid-major-size': scale(96),
    '--callflow-grid-minor-size': scale(24),
    '--callflow-grid-dot-offset': scale(12),
    '--callflow-grid-dot-color': zoom.value < 0.6 ? 'rgb(71 85 105 / 0.1)' : 'rgb(71 85 105 / 0.2)',
  }
})
</script>

<template>
  <div
    class="relative flex h-[calc(100dvh-7rem)] min-h-[36rem] flex-col overflow-hidden bg-slate-50/70"
  >
    <div
      ref="panCanvas"
      data-callflow-pan-canvas
      class="callflow-canvas-texture min-h-0 flex-1 overflow-auto p-4 pt-20 select-none"
      :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'"
      :style="canvasTextureStyle"
      @pointerdown="startPanning"
      @pointermove="pan"
      @pointerup="stopPanning"
      @pointercancel="stopPanning"
      @lostpointercapture="stopPanning"
      @wheel="handleZoomWheel"
    >
      <CallflowCanvasHeader>
        <template #controls>
          <div
            data-callflow-no-pan
            role="group"
            aria-label="Canvas zoom controls"
            class="ml-1 inline-flex h-7 items-center overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm"
          >
            <button
              type="button"
              aria-label="Zoom out"
              title="Zoom out"
              :disabled="!canZoomOut"
              class="grid h-full w-8 place-items-center text-slate-600 transition hover:bg-slate-50 hover:text-brand-600 disabled:cursor-not-allowed disabled:text-slate-300"
              @click="zoomOut"
            >
              <MagnifyingGlassMinusIcon class="size-3.5" />
            </button>
            <button
              type="button"
              aria-label="Reset canvas zoom"
              title="Reset canvas zoom"
              class="h-full min-w-12 border-x border-slate-200 px-2 text-[9px] font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-brand-600"
              @click="resetZoom"
            >
              {{ zoomPercent }}%
            </button>
            <button
              type="button"
              aria-label="Zoom in"
              title="Zoom in"
              :disabled="!canZoomIn"
              class="grid h-full w-8 place-items-center text-slate-600 transition hover:bg-slate-50 hover:text-brand-600 disabled:cursor-not-allowed disabled:text-slate-300"
              @click="zoomIn"
            >
              <MagnifyingGlassPlusIcon class="size-3.5" />
            </button>
          </div>
        </template>
      </CallflowCanvasHeader>
      <div
        role="tree"
        aria-label="Callflow diagram"
        data-callflow-pan-content
        class="mx-auto flex w-max min-w-[calc(100%_+_16rem)] flex-col items-center px-32 sm:min-w-[calc(100%_+_24rem)] sm:px-48 lg:min-w-[calc(100%_+_32rem)] lg:px-64"
        :style="{ zoom: String(zoom) }"
      >
        <div
          v-if="$slots['entry-actions']"
          data-callflow-entry-actions
          class="mb-3 flex w-80 justify-center"
        >
          <slot name="entry-actions" />
        </div>
        <CallflowEntryNode
          :name="entryName"
          :entries="entryPoints"
          :editable="editable"
          edit-label="Edit callflow entry numbers"
          @edit="$emit('edit-entry')"
          @add-entry="$emit('add-entry')"
        />
        <CallflowConnectorArrow />
        <CallflowTreeNode
          :node="node"
          :selected-path="selectedPath"
          :editable="editable"
          :moving="moving"
          :drag-source-path="dragSourcePath"
          :palette-action="paletteAction"
          :capability-guided-modules="capabilityGuidedModules"
          @select="$emit('select', $event)"
          @drag-start="$emit('drag-start', $event)"
          @drag-end="$emit('drag-end')"
          @move="$emit('move', $event)"
          @add-action="
            (selection, action, placement) => $emit('add-action', selection, action, placement)
          "
          @remove="$emit('remove', $event)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.callflow-canvas-texture {
  background-color: rgb(248 250 252);
  background-image:
    linear-gradient(to right, rgb(148 163 184 / 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgb(148 163 184 / 0.1) 1px, transparent 1px),
    radial-gradient(
      circle,
      var(--callflow-grid-dot-color, rgb(71 85 105 / 0.2)) 1px,
      transparent 1.25px
    );
  background-position:
    0 0,
    0 0,
    var(--callflow-grid-dot-offset, 12px) var(--callflow-grid-dot-offset, 12px);
  background-size:
    var(--callflow-grid-major-size, 96px) var(--callflow-grid-major-size, 96px),
    var(--callflow-grid-major-size, 96px) var(--callflow-grid-major-size, 96px),
    var(--callflow-grid-minor-size, 24px) var(--callflow-grid-minor-size, 24px);
}
</style>
