import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { callerIdListApi } from '../api/callerIdListApi'
import type { CallerIdList, CallerIdListInput } from '../types/callerIdList'
import { useCallerIdListStore } from './callerIdListStore'

vi.mock('../api/callerIdListApi', () => ({
  callerIdListApi: {
    list: vi.fn(),
    detail: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    startSync: vi.fn(),
    syncStatus: vi.fn(),
  },
}))

const record: CallerIdList = {
  id: '57b3fe6b-958d-4486-84a0-10abfc1d833d',
  name: 'VIP callers',
  description: null,
  organization: null,
  entry_count: 1,
  sync_status: 'healthy',
  last_synced_at: null,
}
const response = {
  data: [record],
  meta: { current_page: 1, from: 1, last_page: 1, path: '', per_page: 25, to: 1, total: 1 },
  links: { first: null, last: null, prev: null, next: null },
}

describe('callerIdListStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads account-scoped projected lists', async () => {
    vi.mocked(callerIdListApi.list).mockResolvedValue(response)
    const store = useCallerIdListStore()

    await store.load('account-1')

    expect(callerIdListApi.list).toHaveBeenCalledWith('account-1', '', 1)
    expect(store.records).toEqual([record])
  })

  it('creates a list and reloads the projection', async () => {
    const input: CallerIdListInput = {
      name: 'VIP callers',
      description: null,
      organization: null,
      entries: [{ id: null, display_name: 'Support', number: '+1555', pattern: null }],
    }
    vi.mocked(callerIdListApi.create).mockResolvedValue(record)
    vi.mocked(callerIdListApi.list).mockResolvedValue(response)
    const store = useCallerIdListStore()

    expect(await store.save('account-1', input)).toBe(true)
    expect(callerIdListApi.create).toHaveBeenCalledWith('account-1', input)
    expect(store.records).toEqual([record])
  })
})
