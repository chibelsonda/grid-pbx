import { onBeforeUnmount, ref, type Ref } from 'vue'

const PAN_BLOCKING_SELECTOR = [
  'a',
  'button',
  'input',
  'select',
  'textarea',
  '[contenteditable="true"]',
  '[draggable="true"]',
  '[data-callflow-no-pan]',
].join(',')

type PanOrigin = {
  pointerId: number
  clientX: number
  clientY: number
  scrollLeft: number
  scrollTop: number
}

export function useDragToPan(container: Ref<HTMLElement | null>) {
  const isPanning = ref(false)
  let origin: PanOrigin | null = null

  function startPanning(event: PointerEvent): void {
    const element = container.value
    const target = event.target

    if (
      !element ||
      event.button !== 0 ||
      event.pointerType === 'touch' ||
      !(target instanceof Element) ||
      target.closest(PAN_BLOCKING_SELECTOR)
    ) {
      return
    }

    origin = {
      pointerId: event.pointerId,
      clientX: event.clientX,
      clientY: event.clientY,
      scrollLeft: element.scrollLeft,
      scrollTop: element.scrollTop,
    }
    isPanning.value = true

    try {
      element.setPointerCapture?.(event.pointerId)
    } catch {
      // Synthetic pointer events and older browsers may not expose an active pointer to capture.
    }

    event.preventDefault()
  }

  function pan(event: PointerEvent): void {
    const element = container.value
    if (!element || !origin || event.pointerId !== origin.pointerId) return

    element.scrollLeft = origin.scrollLeft - (event.clientX - origin.clientX)
    element.scrollTop = origin.scrollTop - (event.clientY - origin.clientY)
    event.preventDefault()
  }

  function stopPanning(event?: PointerEvent): void {
    const element = container.value
    if (!origin || (event && event.pointerId !== origin.pointerId)) return

    try {
      if (element?.hasPointerCapture?.(origin.pointerId)) {
        element.releasePointerCapture(origin.pointerId)
      }
    } catch {
      // The pointer may already have been released by the browser.
    }

    origin = null
    isPanning.value = false
  }

  onBeforeUnmount(() => stopPanning())

  return {
    isPanning,
    startPanning,
    pan,
    stopPanning,
  }
}
