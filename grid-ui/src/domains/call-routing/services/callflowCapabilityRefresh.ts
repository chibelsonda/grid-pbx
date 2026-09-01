export const callflowCapabilitiesChangedEvent = 'gridpbx:callflow-capabilities-changed'
export const callflowCapabilitiesChangedStorageKey =
  'gridpbx.callflow-capabilities-changed.v1'

export type CallflowCapabilitiesChanged = {
  accountId: string
  changedAt: string
  token: string
}

function isChange(value: unknown): value is CallflowCapabilitiesChanged {
  if (typeof value !== 'object' || value === null) return false

  const candidate = value as Partial<CallflowCapabilitiesChanged>

  return (
    typeof candidate.accountId === 'string' &&
    candidate.accountId.length > 0 &&
    typeof candidate.changedAt === 'string' &&
    typeof candidate.token === 'string'
  )
}

function parseChange(value: string | null): CallflowCapabilitiesChanged | null {
  if (!value) return null

  try {
    const parsed: unknown = JSON.parse(value)

    return isChange(parsed) ? parsed : null
  } catch {
    return null
  }
}

/**
 * Publishes safe account-scoped invalidation only. Private integration settings
 * never cross the browser event boundary.
 */
export function announceCallflowCapabilitiesChanged(accountId: string): void {
  if (typeof window === 'undefined') return

  const detail: CallflowCapabilitiesChanged = {
    accountId,
    changedAt: new Date().toISOString(),
    token: globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`,
  }

  window.dispatchEvent(new CustomEvent(callflowCapabilitiesChangedEvent, { detail }))

  try {
    window.localStorage.setItem(callflowCapabilitiesChangedStorageKey, JSON.stringify(detail))
  } catch {
    // Same-tab listeners still refresh when storage is unavailable.
  }
}

export function listenForCallflowCapabilitiesChanged(
  listener: (change: CallflowCapabilitiesChanged) => void,
): () => void {
  if (typeof window === 'undefined') return () => undefined

  const handleLocal = (event: Event): void => {
    const detail = (event as CustomEvent<unknown>).detail
    if (isChange(detail)) listener(detail)
  }
  const handleStorage = (event: StorageEvent): void => {
    if (event.key !== callflowCapabilitiesChangedStorageKey) return

    const change = parseChange(event.newValue)
    if (change) listener(change)
  }

  window.addEventListener(callflowCapabilitiesChangedEvent, handleLocal)
  window.addEventListener('storage', handleStorage)

  return () => {
    window.removeEventListener(callflowCapabilitiesChangedEvent, handleLocal)
    window.removeEventListener('storage', handleStorage)
  }
}
