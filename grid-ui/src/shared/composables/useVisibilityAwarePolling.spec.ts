import { effectScope, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useVisibilityAwarePolling } from './useVisibilityAwarePolling'

describe('useVisibilityAwarePolling', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'visible',
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('polls only while active and not paused', async () => {
    const active = ref(false)
    const paused = ref(false)
    const task = vi.fn(async () => undefined)
    const scope = effectScope()

    scope.run(() => useVisibilityAwarePolling({ active, paused, intervalMs: 1_000, task }))
    await vi.advanceTimersByTimeAsync(2_000)
    expect(task).not.toHaveBeenCalled()

    active.value = true
    await vi.advanceTimersByTimeAsync(1_000)
    expect(task).toHaveBeenCalledTimes(1)

    paused.value = true
    await vi.advanceTimersByTimeAsync(2_000)
    expect(task).toHaveBeenCalledTimes(1)

    paused.value = false
    await vi.advanceTimersByTimeAsync(1_000)
    expect(task).toHaveBeenCalledTimes(2)
    scope.stop()
  })

  it('pauses while hidden and refreshes immediately when visibility returns', async () => {
    const task = vi.fn(async () => undefined)
    const scope = effectScope()

    scope.run(() => useVisibilityAwarePolling({ active: true, intervalMs: 1_000, task }))
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'hidden',
    })
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(2_000)
    expect(task).not.toHaveBeenCalled()

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'visible',
    })
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(0)
    expect(task).toHaveBeenCalledTimes(1)
    scope.stop()
  })
})
