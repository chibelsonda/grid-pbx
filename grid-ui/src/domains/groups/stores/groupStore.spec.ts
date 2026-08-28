import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { groupApi } from '../api/groupApi'
import type { PaginatedResponse } from '@/shared/api/http'
import type { Group, GroupInput, GroupOptions, GroupSyncRun } from '../types/group'
import { useGroupStore } from './groupStore'

vi.mock('../api/groupApi', () => ({ groupApi: {
  list: vi.fn<() => Promise<PaginatedResponse<Group>>>(),
  detail: vi.fn<() => Promise<Group>>(),
  options: vi.fn<() => Promise<GroupOptions>>(),
  create: vi.fn<() => Promise<Group>>(),
  update: vi.fn<() => Promise<Group>>(),
  remove: vi.fn<() => Promise<void>>(),
  startSync: vi.fn<() => Promise<GroupSyncRun>>(),
  syncStatus: vi.fn<() => Promise<GroupSyncRun>>(),
} }))
const record: Group = { id: 'public-group', name: 'Support', member_count: 1, members: [], music_on_hold_media: null, sync_status: 'healthy', last_synced_at: null }
const options: GroupOptions = { users: [{ id: 'public-user', label: 'Ada', detail: '1001' }], devices: [], groups: [], media: [] }

describe('group store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })
  it('loads account-scoped projected groups', async () => {
    vi.mocked(groupApi.list).mockResolvedValue({ data: [record], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 } })
    const store = useGroupStore(); await store.load('account-1')
    expect(groupApi.list).toHaveBeenCalledWith('account-1', '', 1); expect(store.records).toEqual([record])
  })
  it('creates a group from public member references', async () => {
    vi.mocked(groupApi.options).mockResolvedValue(options); vi.mocked(groupApi.create).mockResolvedValue(record)
    const store = useGroupStore(); await store.prepare('account-1')
    const input: GroupInput = { name: 'Support', music_on_hold_media_id: null, members: [{ type: 'user', id: 'public-user', weight: 1 }] }
    expect(await store.save('account-1', input)).toBe(true); expect(groupApi.create).toHaveBeenCalledWith('account-1', input); expect(store.detail).toEqual(record)
  })
})
