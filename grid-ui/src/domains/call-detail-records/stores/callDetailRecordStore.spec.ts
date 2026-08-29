import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { callDetailRecordApi, type CallDetailRecordPage } from '../api/callDetailRecordApi'
import type { CallDetailRecord, SyncRun } from '../types/callDetailRecord'
import { useCallDetailRecordStore } from './callDetailRecordStore'

vi.mock('../api/callDetailRecordApi', () => ({
  callDetailRecordApi: {
    list: vi.fn<() => Promise<CallDetailRecordPage>>(),
    detail: vi.fn<() => Promise<CallDetailRecord>>(),
    startSync: vi.fn<() => Promise<SyncRun>>(),
    syncStatus: vi.fn<() => Promise<SyncRun>>(),
  },
}))

const record: CallDetailRecord = {
  id: 'record-public-id',
  call_id: 'call-1',
  interaction_id: 'interaction-1',
  direction: 'inbound',
  caller: { name: 'Alice Caller', number: '+14155550100' },
  callee: { name: 'Grid Support', number: '1001' },
  from: 'alice@example.test',
  to: '1001@gridpbx.test',
  request: '1001@gridpbx.test',
  started_at: '2026-08-28T04:00:00Z',
  duration_seconds: 75,
  billing_seconds: 60,
  answered: true,
  hangup_cause: 'NORMAL_CLEARING',
  disposition: 'SUCCESS',
  recording_available: false,
  recordings: [],
  extension: { id: 'extension-public-id', display_name: 'Support Operator', extension: '1001' },
  last_synced_at: '2026-08-28T05:00:00Z',
}

describe('call detail record store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads filtered projected calls and the configured import window', async () => {
    vi.mocked(callDetailRecordApi.list).mockResolvedValue({
      data: [record],
      links: { prev: null, next: null },
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 1,
        sync: { status: 'healthy', last_successful_at: record.last_synced_at, error_message: null },
        import_window_days: 7,
      },
    })
    const store = useCallDetailRecordStore()
    store.filters.search = 'Alice'
    store.filters.direction = 'inbound'

    await store.load('account-1')

    expect(callDetailRecordApi.list).toHaveBeenCalledWith('account-1', store.filters, 1)
    expect(store.records).toEqual([record])
    expect(store.total).toBe(1)
    expect(store.importWindowDays).toBe(7)
    expect(store.loading).toBe(false)
  })

  it('loads and closes a call detail panel', async () => {
    vi.mocked(callDetailRecordApi.detail).mockResolvedValue(record)
    const store = useCallDetailRecordStore()

    await store.loadDetail('account-1', record.id)

    expect(callDetailRecordApi.detail).toHaveBeenCalledWith('account-1', record.id)
    expect(store.detail).toEqual(record)
    store.closeDetail()
    expect(store.detail).toBeNull()
  })
})
