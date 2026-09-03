<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { ChevronLeftIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    dockedClass?: string
  }>(),
  {
    title: 'Route structure',
    dockedClass: 'w-full shadow-xl',
  },
)

defineSlots<{
  default(): unknown
  summary(): unknown
  sidebar(): unknown
  palette(props: {
    floating: boolean
    startMoving: (event: PointerEvent) => void
    dock: () => void
    collapse: () => void
  }): unknown
}>()

const paletteShell = ref<HTMLElement | null>(null)
const paletteFloating = ref(false)
const railCollapsed = ref(false)
const palettePosition = ref({ left: 0, top: 0, width: 184 })
let palettePointerOffset = { x: 0, y: 0 }

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

function collapseRail(): void {
  dockPalette()
  railCollapsed.value = true
}

function expandRail(): void {
  railCollapsed.value = false
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
</script>

<template>
  <div data-callflow-workspace-layout class="grid min-w-0 gap-4">
    <section data-callflow-canvas-shell class="relative min-w-0 overflow-hidden">
      <header
        class="flex min-h-14 flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-4 py-3 sm:px-6 lg:px-8"
      >
        <div class="mr-auto">
          <h2 class="text-sm font-semibold text-slate-700">{{ title }}</h2>
          <p v-if="description" class="mt-0.5 text-[10px] text-heading-description">{{ description }}</p>
        </div>
        <slot name="summary" />
      </header>
      <slot />
      <div
        data-callflow-docked-rail
        class="absolute top-32 right-4 z-20 grid min-w-0 transition-[width]"
        :class="
          railCollapsed
            ? 'w-9 overflow-visible'
            : 'max-h-[calc(100%_-_9rem)] w-[11.5rem] gap-3 overflow-x-hidden overflow-y-auto overscroll-contain pb-4'
        "
      >
        <button
          v-if="railCollapsed"
          type="button"
          aria-label="Expand action catalog and route details"
          title="Show action catalog and route details"
          aria-controls="callflow-docked-rail-content"
          :aria-expanded="false"
          class="grid size-9 place-items-center rounded-l-md border border-r-0 border-slate-700 bg-callflow-node text-slate-200 shadow-lg hover:bg-slate-800 hover:text-white"
          @click="expandRail"
        >
          <ChevronLeftIcon class="size-4" />
        </button>

        <div
          v-show="!railCollapsed"
          id="callflow-docked-rail-content"
          data-callflow-docked-rail-content
          class="relative grid min-w-0 gap-3"
        >
          <div
            ref="paletteShell"
            data-callflow-palette-shell
            :style="paletteStyle"
            :class="
              paletteFloating
                ? 'fixed z-40 overflow-hidden rounded-lg shadow-2xl ring-1 ring-slate-300'
                : ['min-w-0', dockedClass]
            "
          >
            <slot
              name="palette"
              :floating="paletteFloating"
              :start-moving="startMovingPalette"
              :dock="dockPalette"
              :collapse="collapseRail"
            />
          </div>

          <aside
            data-callflow-supporting-cards
            class="grid min-w-0 grid-cols-1 gap-3 [&>*]:w-full [&>*]:min-w-0 [&>*]:break-words"
          >
            <slot name="sidebar" />
          </aside>
        </div>
      </div>
    </section>
  </div>
</template>
