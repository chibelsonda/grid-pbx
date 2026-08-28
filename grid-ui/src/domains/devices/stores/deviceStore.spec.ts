import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { deviceApi, type DevicePage } from '../api/deviceApi'
import type { Device, DeviceInput, ExtensionOption } from '../types/device'
import { useDeviceStore } from './deviceStore'

vi.mock('../api/deviceApi', () => ({
  deviceApi: {
    list: vi.fn<(accountId: string, search?: string, page?: number) => Promise<DevicePage>>(),
    detail: vi.fn<(accountId: string, deviceId: string) => Promise<Device>>(),
    create: vi.fn<(accountId: string, input: DeviceInput) => Promise<Device>>(),
    update: vi.fn<(accountId: string, deviceId: string, input: DeviceInput) => Promise<Device>>(),
    remove: vi.fn<(accountId: string, deviceId: string) => Promise<void>>(),
    extensionOptions: vi.fn<(accountId: string) => Promise<ExtensionOption[]>>(),
  },
}))

const device: Device = {
  id: 'device-1',
  name: 'Reception Desk Phone',
  device_type: 'sip_device',
  make: 'Yealink',
  model: 'T54W',
  mac_address: '00:11:22:33:44:55',
  is_enabled: true,
  assigned_extension: {
    id: 'extension-1',
    display_name: 'Alice Operator',
    extension: '1001',
  },
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T06:30:00Z',
}

describe('device store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('hydrates the account-scoped device page and synchronization metadata', async () => {
    const page: DevicePage = {
      data: [device],
      links: { prev: null, next: null },
      meta: {
        current_page: 2,
        last_page: 3,
        per_page: 25,
        total: 51,
        sync: {
          status: 'healthy',
          last_successful_at: '2026-08-28T06:30:00Z',
          error_message: null,
        },
      },
    }
    vi.mocked(deviceApi.list).mockResolvedValue(page)
    const store = useDeviceStore()
    store.search = 'Alice'

    await store.load('account-1', 2)

    expect(deviceApi.list).toHaveBeenCalledWith('account-1', 'Alice', 2)
    expect(store.records).toEqual([device])
    expect(store.page).toBe(2)
    expect(store.lastPage).toBe(3)
    expect(store.total).toBe(51)
    expect(store.sync.status).toBe('healthy')
    expect(store.loading).toBe(false)
  })

  it('loads a device detail without changing the list projection', async () => {
    vi.mocked(deviceApi.detail).mockResolvedValue(device)
    const store = useDeviceStore()

    await store.loadDetail('account-1', 'device-1')

    expect(deviceApi.detail).toHaveBeenCalledWith('account-1', 'device-1')
    expect(store.detail).toEqual(device)
    expect(store.detailError).toBeNull()
    expect(store.detailLoading).toBe(false)
  })

  it('creates a device and makes it the active detail projection', async () => {
    const input: DeviceInput = {
      name: 'Reception Desk Phone',
      device_type: 'sip_device',
      make: 'Yealink',
      model: 'T54W',
      mac_address: '00:11:22:33:44:55',
      is_enabled: true,
      assigned_extension_id: 'extension-1',
      sip_username: 'reception',
      sip_password: 'a-long-random-secret',
    }
    vi.mocked(deviceApi.create).mockResolvedValue(device)
    const store = useDeviceStore()

    const created = await store.create('account-1', input)

    expect(deviceApi.create).toHaveBeenCalledWith('account-1', input)
    expect(created).toEqual(device)
    expect(store.detail).toEqual(device)
    expect(store.records).toEqual([device])
    expect(store.mutationLoading).toBe(false)
  })
})
