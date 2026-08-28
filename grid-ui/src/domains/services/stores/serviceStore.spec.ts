import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { serviceApi } from '../api/serviceApi'
import { useServiceStore } from './serviceStore'
import type { ServiceOverview } from '../types/service'

vi.mock('../api/serviceApi', () => ({
  serviceApi: { overview: vi.fn(), startSync: vi.fn(), syncStatus: vi.fn() },
}))
const overview: ServiceOverview = {
  id: 'summary-1',
  standing: { acceptable: true, reason: 'good standing' },
  reseller: { is_reseller: false },
  billing_cycle: { next_at: null, period: 1, unit: 'month' },
  billing_impact: { invoice_count: 0, due_today: 0, recurring_amount: 0 },
  plans: [],
  quantities: [],
  limits: null,
  last_synced_at: null,
  sync_status: 'healthy',
}
describe('service store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })
  it('loads a read-only overview', async () => {
    vi.mocked(serviceApi.overview).mockResolvedValue(overview)
    const store = useServiceStore()
    await store.load('account-1')
    expect(store.overview).toEqual(overview)
  })
  it('refreshes after a completed sync', async () => {
    vi.mocked(serviceApi.startSync).mockResolvedValue({
      id: 'run-1',
      status: 'succeeded',
      error_message: null,
    })
    vi.mocked(serviceApi.overview).mockResolvedValue(overview)
    const store = useServiceStore()
    await store.synchronize('account-1')
    expect(serviceApi.overview).toHaveBeenCalledWith('account-1')
  })
})
