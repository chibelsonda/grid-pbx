import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { lineKeyApi } from '../api/lineKeyApi'
import type { LineKeyDevice, LineKeyPreview } from '../types/lineKey'
import { useLineKeyStore } from './lineKeyStore'

vi.mock('../api/lineKeyApi', () => ({
  lineKeyApi: {
    list: vi.fn(),
    preview: vi.fn(),
    startSync: vi.fn(),
    syncStatus: vi.fn(),
    update: vi.fn(),
  },
}))

const device: LineKeyDevice = {
  id: 'device-public-id',
  name: 'Reception phone',
  make: 'Yealink',
  endpoint_family: 'T5',
  model: 'T54W',
  mac_address: '00:11:22:33:44:55',
  line_keys: [
    {
      id: 'key-public-id',
      category: 'feature',
      position: 1,
      type: 'speed_dial',
      label: 'Support',
      value: '1000',
    },
  ],
}

const listResponse = {
  data: [device],
  meta: {
    sync: {
      status: 'healthy' as const,
      last_successful_at: '2026-08-31T07:00:00Z',
      error_message: null,
    },
  },
}

const succeededRun = {
  id: 'sync-run-public-id',
  resource_type: 'extensions',
  status: 'succeeded' as const,
  processed_count: 5,
  upserted_count: 4,
  deleted_count: 1,
  error_message: null,
  started_at: '2026-08-31T07:00:00Z',
  finished_at: '2026-08-31T07:00:01Z',
  created_at: '2026-08-31T07:00:00Z',
}

describe('line key store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads the device-owned line-key inventory', async () => {
    vi.mocked(lineKeyApi.list).mockResolvedValue(listResponse)
    const store = useLineKeyStore()
    store.search = 'Reception'
    await store.load('account-public-id')

    expect(lineKeyApi.list).toHaveBeenCalledWith('account-public-id', 'Reception')
    expect(store.records).toEqual([device])
    expect(store.sync).toEqual(listResponse.meta.sync)
  })

  it('loads a capability-aware preview before opening the editor', async () => {
    const preview: LineKeyPreview = {
      device,
      capability: {
        preview_available: true,
        apply_available: false,
        reason: 'Disabled locally.',
        model: {
          catalog_available: false,
          catalog_reason: 'Provisioning catalog discovery is not configured.',
          matched: false,
          max_keys: null,
          max_expansion_modules: null,
          keys_per_expansion_module: null,
          total_keys: null,
          supported_key_types: ['line', 'presence', 'personal_parking', 'speed_dial', 'parking'],
          value_sources: [],
          manufacturer_provider: null,
        },
      },
      value_choices: [],
      payload_preview: { provision: { combo_keys: {}, feature_keys: {} } },
    }
    vi.mocked(lineKeyApi.preview).mockResolvedValue(preview)
    const store = useLineKeyStore()
    await store.prepare('account-public-id', device.id)

    expect(store.preview).toEqual(preview)
    expect(store.preview?.capability.apply_available).toBe(false)
  })

  it('reuses the extension and device synchronization before refreshing line keys', async () => {
    vi.mocked(lineKeyApi.startSync).mockResolvedValue(succeededRun)
    vi.mocked(lineKeyApi.list).mockResolvedValue(listResponse)
    const store = useLineKeyStore()

    await store.synchronize('account-public-id')

    expect(lineKeyApi.startSync).toHaveBeenCalledWith('account-public-id')
    expect(lineKeyApi.syncStatus).not.toHaveBeenCalled()
    expect(lineKeyApi.list).toHaveBeenCalledWith('account-public-id', '')
    expect(store.records).toEqual([device])
    expect(store.syncRun).toEqual(succeededRun)
    expect(store.sync).toEqual(listResponse.meta.sync)
    expect(store.synchronizing).toBe(false)
  })

  it('polls a queued synchronization and retains safe completion counts', async () => {
    vi.spyOn(window, 'setTimeout').mockImplementation((callback: TimerHandler) => {
      if (typeof callback === 'function') callback()
      return 1
    })
    vi.mocked(lineKeyApi.startSync).mockResolvedValue({
      ...succeededRun,
      status: 'queued',
      processed_count: 0,
      upserted_count: 0,
      deleted_count: 0,
      finished_at: null,
    })
    vi.mocked(lineKeyApi.syncStatus)
      .mockResolvedValueOnce({ ...succeededRun, status: 'running', finished_at: null })
      .mockResolvedValueOnce(succeededRun)
    vi.mocked(lineKeyApi.list).mockResolvedValue(listResponse)
    const store = useLineKeyStore()

    await store.synchronize('account-public-id')

    expect(lineKeyApi.syncStatus).toHaveBeenCalledTimes(2)
    expect(store.syncRun).toEqual(succeededRun)
    expect(store.error).toBeNull()
  })

  it('shows the API-safe message when synchronization fails', async () => {
    vi.mocked(lineKeyApi.startSync).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Synchronization could not be started.' } },
    })
    const store = useLineKeyStore()

    await store.synchronize('account-public-id')

    expect(store.error).toBe('Synchronization could not be started.')
    expect(store.sync).toMatchObject({
      status: 'error',
      error_message: 'Synchronization could not be started.',
    })
    expect(store.synchronizing).toBe(false)
  })

  it('keeps API validation errors inline without a duplicate mutation alert', async () => {
    const preview: LineKeyPreview = {
      device,
      capability: {
        preview_available: true,
        apply_available: true,
        reason: null,
        model: {
          catalog_available: false,
          catalog_reason: 'Provisioning catalog discovery is not configured.',
          matched: false,
          max_keys: null,
          max_expansion_modules: null,
          keys_per_expansion_module: null,
          total_keys: null,
          supported_key_types: ['speed_dial'],
          value_sources: [],
          manufacturer_provider: null,
        },
      },
      value_choices: [],
      payload_preview: { provision: { combo_keys: {}, feature_keys: {} } },
    }
    vi.mocked(lineKeyApi.update).mockRejectedValue({
      isAxiosError: true,
      response: {
        data: {
          message: 'Invalid data.',
          errors: { 'line_keys.0.value': ['Enter a supported value.'] },
        },
      },
    })
    const store = useLineKeyStore()
    store.preview = preview

    await store.save('account-1', device.line_keys)

    expect(store.fieldErrors).toEqual({
      'line_keys.0.value': ['Enter a supported value.'],
    })
    expect(store.mutationError).toBeNull()
  })
})
