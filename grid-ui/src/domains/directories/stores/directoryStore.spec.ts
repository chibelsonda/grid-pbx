import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { directoryApi } from '../api/directoryApi'
import type { PaginatedResponse } from '@/shared/api/http'
import type {
  Directory,
  DirectoryInput,
  DirectoryOptions,
  DirectorySyncRun,
} from '../types/directory'
import { useDirectoryStore } from './directoryStore'

vi.mock('../api/directoryApi', () => ({
  directoryApi: {
    list: vi.fn<() => Promise<PaginatedResponse<Directory>>>(),
    detail: vi.fn<() => Promise<Directory>>(),
    options: vi.fn<() => Promise<DirectoryOptions>>(),
    create: vi.fn<() => Promise<Directory>>(),
    update: vi.fn<() => Promise<Directory>>(),
    remove: vi.fn<() => Promise<void>>(),
    startSync: vi.fn<() => Promise<DirectorySyncRun>>(),
    syncStatus: vi.fn<() => Promise<DirectorySyncRun>>(),
  },
}))
const record: Directory = {
  id: 'public-directory',
  name: 'People',
  confirm_match: true,
  min_dtmf: 3,
  max_dtmf: 0,
  sort_by: 'last_name',
  flags: ['public-directory'],
  member_count: 1,
  members: [],
  sync_status: 'healthy',
  last_synced_at: null,
}
const options: DirectoryOptions = {
  extensions: [{ id: 'public-extension', label: 'Ada', detail: '1001' }],
}

describe('directory store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })
  it('loads account-scoped projected directories', async () => {
    vi.mocked(directoryApi.list).mockResolvedValue({
      data: [record],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
    })
    const store = useDirectoryStore()
    await store.load('account-1')
    expect(directoryApi.list).toHaveBeenCalledWith('account-1', '', 1)
    expect(store.records).toEqual([record])
  })
  it('prepares public options and creates through the slide-over workflow', async () => {
    vi.mocked(directoryApi.options).mockResolvedValue(options)
    vi.mocked(directoryApi.create).mockResolvedValue(record)
    const store = useDirectoryStore()
    await store.prepare('account-1')
    const input: DirectoryInput = {
      name: 'People',
      confirm_match: true,
      min_dtmf: 3,
      max_dtmf: 0,
      sort_by: 'last_name',
      member_ids: ['public-extension'],
    }
    expect(await store.save('account-1', input)).toBe(true)
    expect(store.options).toEqual(options)
    expect(store.detail).toEqual(record)
  })

  it('keeps API validation errors inline without a duplicate mutation alert', async () => {
    vi.mocked(directoryApi.create).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Invalid data.', errors: { name: ['Enter a name.'] } } },
    })
    const store = useDirectoryStore()

    await store.save('account-1', {
      name: '',
      confirm_match: true,
      min_dtmf: 3,
      max_dtmf: 0,
      sort_by: 'last_name',
      member_ids: [],
    })

    expect(store.fieldErrors).toEqual({ name: ['Enter a name.'] })
    expect(store.mutationError).toBeNull()
  })
})
