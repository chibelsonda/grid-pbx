import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { PaginatedResponse } from '@/shared/api/http'
import { menuApi } from '../api/menuApi'
import type { Menu, MenuInput, MenuOptions, MenuSyncRun } from '../types/menu'
import { useMenuStore } from './menuStore'

vi.mock('../api/menuApi', () => ({
  menuApi: {
    list: vi.fn<() => Promise<PaginatedResponse<Menu>>>(),
    detail: vi.fn<() => Promise<Menu>>(),
    options: vi.fn<() => Promise<MenuOptions>>(),
    create: vi.fn<() => Promise<Menu>>(),
    update: vi.fn<() => Promise<Menu>>(),
    remove: vi.fn<() => Promise<void>>(),
    startSync: vi.fn<() => Promise<MenuSyncRun>>(),
    syncStatus: vi.fn<() => Promise<MenuSyncRun>>(),
  },
}))

const record: Menu = {
  id: 'public-menu',
  name: 'Main menu',
  timeout: 10000,
  interdigit_timeout: 2000,
  max_extension_length: 4,
  retries: 3,
  hunt: true,
  allow_record_from_offnet: false,
  suppress_media: false,
  record_pin_configured: false,
  hunt_allow: null,
  hunt_deny: null,
  greeting_media: null,
  greeting_media_unresolved: false,
  invalid_media_enabled: true,
  invalid_media: null,
  invalid_media_unresolved: false,
  transfer_media_enabled: true,
  transfer_media: null,
  transfer_media_unresolved: false,
  exit_media_enabled: true,
  exit_media: null,
  exit_media_unresolved: false,
  sync_status: 'healthy',
  last_synced_at: null,
}
const input: MenuInput = {
  name: 'Main menu',
  timeout: 10000,
  interdigit_timeout: 2000,
  max_extension_length: 4,
  retries: 3,
  hunt: true,
  allow_record_from_offnet: false,
  suppress_media: false,
  record_pin: null,
  clear_record_pin: false,
  hunt_allow: null,
  hunt_deny: null,
  greeting_media_id: null,
  clear_greeting_media: false,
  invalid_media_enabled: true,
  invalid_media_id: null,
  clear_invalid_media: false,
  transfer_media_enabled: true,
  transfer_media_id: null,
  clear_transfer_media: false,
  exit_media_enabled: true,
  exit_media_id: null,
  clear_exit_media: false,
}
const response: PaginatedResponse<Menu> = {
  data: [record],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
}

describe('menu store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads account-scoped menus', async () => {
    vi.mocked(menuApi.list).mockResolvedValue(response)
    const store = useMenuStore()
    await store.load('account-1')
    expect(menuApi.list).toHaveBeenCalledWith('account-1', '', 1)
    expect(store.records).toEqual([record])
  })

  it('creates a menu with public media references', async () => {
    vi.mocked(menuApi.options).mockResolvedValue({ media: [] })
    vi.mocked(menuApi.create).mockResolvedValue(record)
    vi.mocked(menuApi.list).mockResolvedValue(response)
    const store = useMenuStore()
    await store.prepare('account-1')
    expect(await store.save('account-1', input)).toBe(true)
    expect(menuApi.create).toHaveBeenCalledWith('account-1', input)
  })

  it('keeps API validation errors inline without a duplicate mutation alert', async () => {
    vi.mocked(menuApi.create).mockRejectedValue({
      isAxiosError: true,
      response: {
        data: {
          message: 'The given data was invalid.',
          errors: { name: ['Enter a menu name.'] },
        },
      },
    })
    const store = useMenuStore()

    expect(await store.save('account-1', { ...input, name: '' })).toBe(false)
    expect(store.fieldErrors.name).toEqual(['Enter a menu name.'])
    expect(store.mutationError).toBeNull()
  })
})
