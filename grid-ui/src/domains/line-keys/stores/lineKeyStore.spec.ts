import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { lineKeyApi } from '../api/lineKeyApi'
import type { LineKeyDevice, LineKeyPreview } from '../types/lineKey'
import { useLineKeyStore } from './lineKeyStore'

vi.mock('../api/lineKeyApi', () => ({
  lineKeyApi: {
    list: vi.fn(),
    preview: vi.fn(),
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

describe('line key store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads the device-owned line-key inventory', async () => {
    vi.mocked(lineKeyApi.list).mockResolvedValue([device])
    const store = useLineKeyStore()
    store.search = 'Reception'
    await store.load('account-public-id')

    expect(lineKeyApi.list).toHaveBeenCalledWith('account-public-id', 'Reception')
    expect(store.records).toEqual([device])
  })

  it('loads a capability-aware preview before opening the editor', async () => {
    const preview: LineKeyPreview = {
      device,
      capability: { preview_available: true, apply_available: false, reason: 'Disabled locally.' },
      payload_preview: { provision: { combo_keys: {}, feature_keys: {} } },
    }
    vi.mocked(lineKeyApi.preview).mockResolvedValue(preview)
    const store = useLineKeyStore()
    await store.prepare('account-public-id', device.id)

    expect(store.preview).toEqual(preview)
    expect(store.preview?.capability.apply_available).toBe(false)
  })
})
