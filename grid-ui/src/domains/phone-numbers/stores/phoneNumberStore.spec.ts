import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { phoneNumberApi, type PhoneNumberPage } from '../api/phoneNumberApi'
import type { PhoneNumber, SyncRun } from '../types/phoneNumber'
import { usePhoneNumberStore } from './phoneNumberStore'

vi.mock('../api/phoneNumberApi', () => ({
  phoneNumberApi: {
    list: vi.fn<
      (
        accountId: string,
        filters: {
          search: string
          state: string
          assignment: string
          feature: string
        },
        page?: number,
      ) => Promise<PhoneNumberPage>
    >(),
    detail: vi.fn<(accountId: string, phoneNumberId: string) => Promise<PhoneNumber>>(),
    startSync: vi.fn<(accountId: string) => Promise<SyncRun>>(),
    syncStatus: vi.fn<(accountId: string, runId: string) => Promise<SyncRun>>(),
  },
}))

const phoneNumber: PhoneNumber = {
  id: '2baf74c0-70dc-486f-a345-e910034e032c',
  number: '+15551234567',
  state: 'in_service',
  used_by: 'callflow',
  carrier_name: 'Test Carrier',
  features: ['cnam', 'e911'],
  cnam: { display_name: 'GridPBX', inbound_lookup: true },
  e911: {
    status: 'PROVISIONED',
    caller_name: 'GridPBX Reception',
    street_address: '100 Main Street',
    extended_address: null,
    locality: 'San Francisco',
    region: 'CA',
    postal_code: '94105',
    notification_contact_emails: ['ops@example.test'],
  },
  porting: { active: false, requested_port_date: null, service_provider: null },
  capabilities: {
    available_features: ['cnam', 'e911'],
    cnam: { available: true, writable: false, reason: 'Policy gated.' },
    e911: { available: true, writable: false, reason: 'Policy gated.' },
    porting: { available: false, writable: false, reason: 'Unavailable.' },
    purchasing: { available: false, writable: false, reason: 'Unavailable.' },
    release: { available: false, writable: false, reason: 'Unavailable.' },
  },
  assigned_callflow: {
    id: 'be945751-ec72-413d-9263-e793440b189c',
    name: 'Main number',
    numbers: ['+15551234567'],
  },
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T09:00:00+08:00',
}

describe('phone number store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads filtered account inventory and synchronization metadata', async () => {
    const page: PhoneNumberPage = {
      data: [phoneNumber],
      links: { prev: null, next: null },
      meta: {
        current_page: 2,
        last_page: 3,
        per_page: 25,
        total: 51,
        sync: {
          status: 'healthy',
          last_successful_at: '2026-08-28T09:00:00+08:00',
          error_message: null,
        },
      },
    }
    vi.mocked(phoneNumberApi.list).mockResolvedValue(page)
    const store = usePhoneNumberStore()
    store.filters.search = '555123'
    store.filters.assignment = 'assigned'

    await store.load('account-1', 2)

    expect(phoneNumberApi.list).toHaveBeenCalledWith('account-1', store.filters, 2)
    expect(store.records).toEqual([phoneNumber])
    expect(store.page).toBe(2)
    expect(store.total).toBe(51)
    expect(store.sync.status).toBe('healthy')
  })

  it('loads a public-identifier detail for the slide-over', async () => {
    vi.mocked(phoneNumberApi.detail).mockResolvedValue(phoneNumber)
    const store = usePhoneNumberStore()

    await store.loadDetail('account-1', phoneNumber.id)

    expect(phoneNumberApi.detail).toHaveBeenCalledWith('account-1', phoneNumber.id)
    expect(store.detail).toEqual(phoneNumber)
    expect(store.detailLoading).toBe(false)
  })

  it('reloads inventory after a completed synchronization', async () => {
    vi.mocked(phoneNumberApi.startSync).mockResolvedValue({
      id: 'run-1',
      resource_type: 'phone_numbers',
      status: 'succeeded',
      error_message: null,
    })
    vi.mocked(phoneNumberApi.list).mockResolvedValue({
      data: [],
      links: { prev: null, next: null },
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 0,
        sync: { status: 'healthy', last_successful_at: null, error_message: null },
      },
    })
    const store = usePhoneNumberStore()

    await store.synchronize('account-1')

    expect(phoneNumberApi.startSync).toHaveBeenCalledWith('account-1')
    expect(phoneNumberApi.list).toHaveBeenCalledWith('account-1', store.filters, 1)
    expect(store.synchronizing).toBe(false)
    expect(store.error).toBeNull()
  })
})
