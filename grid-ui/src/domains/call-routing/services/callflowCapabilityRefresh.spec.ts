import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  announceCallflowCapabilitiesChanged,
  callflowCapabilitiesChangedStorageKey,
  listenForCallflowCapabilitiesChanged,
} from './callflowCapabilityRefresh'

describe('callflow capability refresh events', () => {
  beforeEach(() => window.localStorage.clear())

  it('notifies the current tab without publishing private integration settings', () => {
    const listener = vi.fn()
    const stop = listenForCallflowCapabilitiesChanged(listener)

    announceCallflowCapabilitiesChanged('account-public-id')

    expect(listener).toHaveBeenCalledWith(
      expect.objectContaining({ accountId: 'account-public-id' }),
    )
    expect(window.localStorage.getItem(callflowCapabilitiesChangedStorageKey)).not.toContain(
      'voice_url',
    )
    stop()
  })

  it('accepts an account-scoped invalidation from another browser tab', () => {
    const listener = vi.fn()
    const stop = listenForCallflowCapabilitiesChanged(listener)
    const change = {
      accountId: 'account-public-id',
      changedAt: '2026-09-01T10:00:00.000Z',
      token: 'event-token',
    }

    window.dispatchEvent(
      new StorageEvent('storage', {
        key: callflowCapabilitiesChangedStorageKey,
        newValue: JSON.stringify(change),
      }),
    )

    expect(listener).toHaveBeenCalledWith(change)
    stop()
  })
})
