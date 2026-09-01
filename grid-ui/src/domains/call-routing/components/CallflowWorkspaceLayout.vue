<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'

withDefaults(
  defineProps<{
    title?: string
    dockedClass?: string
  }>(),
  {
    title: 'Route structure',
    dockedClass: 'xl:sticky xl:top-3',
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
  }): unknown
}>()

const paletteShell = ref<HTMLElement | null>(null)
const paletteFloating = ref(false)
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
  <div
    data-callflow-workspace-layout
    class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_11.5rem] xl:items-start"
  >
    <section class="min-w-0">
      <header
        class="flex min-h-14 flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-4 py-3 sm:px-5"
      >
        <h2 class="mr-auto text-sm font-semibold text-slate-700">{{ title }}</h2>
        <slot name="summary" />
      </header>
      <slot />
    </section>

    <aside class="grid min-w-0 gap-4">
      <div
        ref="paletteShell"
        data-callflow-palette-shell
        :style="paletteStyle"
        :class="
          paletteFloating
            ? 'fixed z-40 overflow-hidden rounded-lg shadow-2xl ring-1 ring-slate-300'
            : dockedClass
        "
      >
        <slot
          name="palette"
          :floating="paletteFloating"
          :start-moving="startMovingPalette"
          :dock="dockPalette"
        />
      </div>
      <slot name="sidebar" />
    </aside>
  </div>
</template>
