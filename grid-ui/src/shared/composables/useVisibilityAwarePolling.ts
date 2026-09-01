import { onScopeDispose, toValue, watch, type MaybeRefOrGetter } from 'vue'

type VisibilityAwarePollingOptions = {
  active: MaybeRefOrGetter<boolean>
  paused?: MaybeRefOrGetter<boolean>
  intervalMs?: number
  task: () => Promise<void>
}

export function useVisibilityAwarePolling({
  active,
  paused = false,
  intervalMs = 5_000,
  task,
}: VisibilityAwarePollingOptions) {
  let timer: ReturnType<typeof window.setTimeout> | null = null
  let running = false
  let stopped = false

  function isVisible(): boolean {
    return typeof document === 'undefined' || document.visibilityState === 'visible'
  }

  function clearTimer(): void {
    if (timer === null) return
    window.clearTimeout(timer)
    timer = null
  }

  function canRun(): boolean {
    return !stopped && toValue(active) && !toValue(paused) && isVisible()
  }

  function schedule(): void {
    clearTimer()
    if (!canRun()) return
    timer = window.setTimeout(() => void runNow(), intervalMs)
  }

  async function runNow(): Promise<void> {
    clearTimer()
    if (!canRun() || running) return

    running = true
    try {
      await task()
    } finally {
      running = false
      schedule()
    }
  }

  function handleVisibilityChange(): void {
    clearTimer()
    if (isVisible()) void runNow()
  }

  const stopWatch = watch([() => toValue(active), () => toValue(paused)], () => schedule(), {
    immediate: true,
  })

  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', handleVisibilityChange)
  }

  function stop(): void {
    if (stopped) return
    stopped = true
    clearTimer()
    stopWatch()
    if (typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
  }

  onScopeDispose(stop)

  return { runNow, stop }
}
