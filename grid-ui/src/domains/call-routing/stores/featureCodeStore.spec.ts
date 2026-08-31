import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { featureCodeApi } from '../api/featureCodeApi'
import type { FeatureCodePage } from '../types/featureCode'
import { useFeatureCodeStore } from './featureCodeStore'

vi.mock('../api/featureCodeApi', () => ({
  featureCodeApi: {
    list: vi.fn<() => Promise<FeatureCodePage>>(),
  },
}))

const page: FeatureCodePage = {
  data: [
    {
      id: '9b27808d-7f2b-40d0-b48e-cce5798548d7',
      numbers: ['*11'],
      patterns: [],
      root_module: 'hotdesk',
      feature_code: { name: 'hotdesk[action=login]', number: '11' },
      sync_status: 'healthy',
      last_synced_at: null,
    },
  ],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 100,
    total: 1,
    sync: {
      status: 'healthy',
      last_successful_at: '2026-08-31T00:00:00Z',
      error_message: null,
      scope: 'pbx_projection',
    },
  },
}

describe('feature code store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads the account-scoped read-only inventory and projection freshness', async () => {
    vi.mocked(featureCodeApi.list).mockResolvedValue(page)
    const store = useFeatureCodeStore()

    await store.load('account-public-uuid')

    expect(featureCodeApi.list).toHaveBeenCalledWith('account-public-uuid')
    expect(store.records).toEqual(page.data)
    expect(store.total).toBe(1)
    expect(store.lastSuccessfulAt).toBe('2026-08-31T00:00:00Z')
  })
})
