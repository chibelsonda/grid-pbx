import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { PaginatedResponse } from '@/shared/api/http'
import { blacklistApi } from '../api/blacklistApi'
import type { Blacklist, BlacklistInput, BlacklistSyncRun } from '../types/blacklist'
import { useBlacklistStore } from './blacklistStore'

vi.mock('../api/blacklistApi', () => ({ blacklistApi: { list: vi.fn<() => Promise<PaginatedResponse<Blacklist>>>(), detail: vi.fn<() => Promise<Blacklist>>(), create: vi.fn<() => Promise<Blacklist>>(), update: vi.fn<() => Promise<Blacklist>>(), remove: vi.fn<() => Promise<void>>(), startSync: vi.fn<() => Promise<BlacklistSyncRun>>(), syncStatus: vi.fn<() => Promise<BlacklistSyncRun>>() } }))
const record: Blacklist = { id: 'public-blacklist', name: 'Spam', should_block_anonymous: true, is_active: true, number_count: 1, numbers: [{ id: 'public-entry', number: '+15550001000' }], sync_status: 'healthy', last_synced_at: null }
const input: BlacklistInput = { name: 'Spam', should_block_anonymous: true, is_active: true, numbers: ['+15550001000'] }
const response: PaginatedResponse<Blacklist> = { data: [record], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 } }
describe('blacklist store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })
  it('loads account-scoped blacklists', async () => { vi.mocked(blacklistApi.list).mockResolvedValue(response); const store = useBlacklistStore(); await store.load('account-1'); expect(blacklistApi.list).toHaveBeenCalledWith('account-1', '', 1); expect(store.records).toEqual([record]) })
  it('creates blacklist data and account activation in one form', async () => { vi.mocked(blacklistApi.create).mockResolvedValue(record); vi.mocked(blacklistApi.list).mockResolvedValue(response); const store = useBlacklistStore(); expect(await store.save('account-1', input)).toBe(true); expect(blacklistApi.create).toHaveBeenCalledWith('account-1', input) })
})
