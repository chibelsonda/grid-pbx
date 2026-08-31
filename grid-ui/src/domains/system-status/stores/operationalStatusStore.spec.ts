import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { operationalStatusApi } from '../api/operationalStatusApi'
import { useOperationalStatusStore } from './operationalStatusStore'

vi.mock('../api/operationalStatusApi', () => ({
  operationalStatusApi: { get: vi.fn() },
}))

describe('operationalStatusStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads and resets the operational summary', async () => {
    const status = {
      observed_at: '2026-08-31T08:00:00+00:00',
      presence: {
        subscription_diagnostics_available: true,
        live_status_available: false as const,
        commands_available: false as const,
      },
      parking: {
        summary_available: true,
        active_call_count: 0,
        actions_available: false as const,
      },
      webhooks: {
        event_catalog_available: true,
        available_event_count: 9,
        configuration_summary_available: true,
        configured_count: 0,
        enabled_count: 0,
        configuration_mutations_available: false as const,
        delivery_history_available: false as const,
      },
      messaging: {
        sms_inventory_available: false,
        mms_inventory_available: false,
        message_content_available: false as const,
        sending_available: false as const,
      },
      number_porting: {
        inventory_available: true,
        request_details_available: false as const,
        documents_available: false as const,
        workflow_mutations_available: false as const,
      },
      number_management: {
        carrier_configuration_available: true,
        search_available: false as const,
        purchase_available: false as const,
        reservation_available: false as const,
        release_available: false as const,
      },
    }
    vi.mocked(operationalStatusApi.get).mockResolvedValue(status)
    const store = useOperationalStatusStore()

    await store.load('account-public-id')

    expect(operationalStatusApi.get).toHaveBeenCalledWith('account-public-id')
    expect(store.status).toEqual(status)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()

    store.reset()
    expect(store.status).toBeNull()
  })
})
