import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import {
  deviceApi,
  type DevicePage,
  type DeviceProvisioningCommand,
} from '../api/deviceApi'
import type { Device, DeviceInput, DeviceOptions } from '../types/device'
import { useDeviceStore } from './deviceStore'
import { legacyDeviceSchemaCompatibility } from '../deviceForm'

vi.mock('../api/deviceApi', () => ({
  deviceApi: {
    list: vi.fn<(accountId: string, search?: string, page?: number) => Promise<DevicePage>>(),
    detail: vi.fn<(accountId: string, deviceId: string) => Promise<Device>>(),
    create: vi.fn<(accountId: string, input: DeviceInput) => Promise<Device>>(),
    update: vi.fn<(accountId: string, deviceId: string, input: DeviceInput) => Promise<Device>>(),
    remove: vi.fn<(accountId: string, deviceId: string) => Promise<void>>(),
    syncProvisioning:
      vi.fn<
        (
          accountId: string,
          deviceId: string,
          command: DeviceProvisioningCommand,
        ) => Promise<{ message: string; command: DeviceProvisioningCommand }>
      >(),
    options: vi.fn<(accountId: string) => Promise<DeviceOptions>>(),
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
  registration_status: 'registered',
  registration_checked_at: '2026-08-28T06:30:00Z',
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

  it('loads assignment and dynamic restriction options together', async () => {
    vi.mocked(deviceApi.options).mockResolvedValue({
      extensions: [{ id: 'extension-1', display_name: 'Alice Operator', extension: '1001' }],
      media: [{ id: 'media-1', name: 'Office music' }],
      caller_id_numbers: [
        {
          id: 'number-1',
          number: '+15551234567',
          display_name: 'Main line',
          e911_enabled: true,
        },
      ],
      provisioning_catalog: {
        available: true,
        reason: null,
        brands: [
          {
            id: 'yealink',
            name: 'Yealink',
            families: [
              {
                id: 't5',
                name: 'T5',
                models: [{ id: 't54w', name: 'T54W', template_id: 'yealink_t5_t54w' }],
              },
            ],
          },
        ],
      },
      restrictions: [{ key: 'international', label: 'International', emergency: false }],
      device_schema: legacyDeviceSchemaCompatibility,
    })
    const store = useDeviceStore()

    await store.loadOptions('account-1')

    expect(deviceApi.options).toHaveBeenCalledWith('account-1')
    expect(store.extensionOptions[0]?.id).toBe('extension-1')
    expect(store.mediaOptions[0]?.id).toBe('media-1')
    expect(store.callerIdNumberOptions[0]?.number).toBe('+15551234567')
    expect(store.provisioningCatalog.brands[0]?.id).toBe('yealink')
    expect(store.restrictionOptions[0]?.key).toBe('international')
  })

  it('creates a device and makes it the active detail projection', async () => {
    const input: DeviceInput = {
      name: 'Reception Desk Phone',
      device_type: 'sip_device',
      provision: {
        endpoint_brand: 'Yealink',
        endpoint_family: null,
        endpoint_model: 'T54W',
        check_sync_event: null,
        check_sync_reload: null,
        check_sync_reboot: null,
      },
      mac_address: '00:11:22:33:44:55',
      is_enabled: true,
      assigned_extension_id: 'extension-1',
      sip: {
        method: 'password',
        username: 'reception',
        password: 'a-long-random-secret',
        realm: null,
        expire_seconds: 300,
        invite_format: 'contact',
        ip: null,
        number: null,
        route: null,
        static_route: null,
        ignore_completed_elsewhere: false,
        custom_sip_headers: { in: [], out: [] },
      },
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

  it('sends an explicit provisioning command and exposes its success message', async () => {
    vi.mocked(deviceApi.syncProvisioning).mockResolvedValue({
      message: 'Switch accepted the device synchronization request.',
      command: 'sync',
    })
    const store = useDeviceStore()

    const succeeded = await store.syncProvisioning('account-1', 'device-1', 'sync')

    expect(deviceApi.syncProvisioning).toHaveBeenCalledWith('account-1', 'device-1', 'sync')
    expect(succeeded).toBe(true)
    expect(store.operationMessage).toContain('synchronization')
    expect(store.mutationLoading).toBe(false)
  })
})
