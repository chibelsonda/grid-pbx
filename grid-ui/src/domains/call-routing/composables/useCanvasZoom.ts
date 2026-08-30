import { computed, ref } from 'vue'

const MIN_ZOOM = 0.4
const MAX_ZOOM = 1.6
const ZOOM_STEP = 0.1

function clampZoom(value: number): number {
  return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Number(value.toFixed(2))))
}

export function useCanvasZoom() {
  const zoom = ref(1)
  const zoomPercent = computed(() => Math.round(zoom.value * 100))
  const canZoomIn = computed(() => zoom.value < MAX_ZOOM)
  const canZoomOut = computed(() => zoom.value > MIN_ZOOM)

  function zoomIn(): void {
    zoom.value = clampZoom(zoom.value + ZOOM_STEP)
  }

  function zoomOut(): void {
    zoom.value = clampZoom(zoom.value - ZOOM_STEP)
  }

  function resetZoom(): void {
    zoom.value = 1
  }

  function handleZoomWheel(event: WheelEvent): void {
    if (!event.ctrlKey && !event.metaKey) return

    event.preventDefault()
    if (event.deltaY < 0) zoomIn()
    if (event.deltaY > 0) zoomOut()
  }

  return {
    zoom,
    zoomPercent,
    canZoomIn,
    canZoomOut,
    zoomIn,
    zoomOut,
    resetZoom,
    handleZoomWheel,
  }
}
