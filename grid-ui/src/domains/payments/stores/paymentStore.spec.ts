import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { paymentApi } from '../api/paymentApi'
import { usePaymentStore } from './paymentStore'

vi.mock('../api/paymentApi', () => ({
  paymentApi: {
    capabilities: vi.fn(),
    attempts: vi.fn(),
    sandboxCharge: vi.fn(),
    sandboxVoid: vi.fn(),
    sandboxRefund: vi.fn(),
    createSandboxCustomerProfile: vi.fn(),
  },
}))

describe('payment store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('sends only opaque payment data with a generated idempotency key', async () => {
    vi.spyOn(crypto, 'randomUUID').mockReturnValue('00000000-0000-4000-8000-000000000001')
    vi.mocked(paymentApi.sandboxCharge).mockResolvedValue({
      id: '00000000-0000-4000-8000-000000000002',
      source_attempt_id: null,
      provider: 'authorize_net',
      operation: 'charge',
      amount: '1.00000000',
      currency: 'USD',
      status: 'succeeded',
      safe_error_code: null,
      completed_at: null,
      created_at: null,
    })
    const opaqueData = {
      dataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT' as const,
      dataValue: 'one-time-provider-token',
    }

    const store = usePaymentStore()
    await store.sandboxCharge('account-public-id', 100, opaqueData)

    expect(paymentApi.sandboxCharge).toHaveBeenCalledWith(
      'account-public-id',
      100,
      opaqueData,
      '00000000-0000-4000-8000-000000000001',
    )
    expect(store.latestAttempt?.status).toBe('succeeded')
  })

  it('uses only the public source attempt id for guarded reversal operations', async () => {
    vi.spyOn(crypto, 'randomUUID').mockReturnValue('00000000-0000-4000-8000-000000000003')
    vi.mocked(paymentApi.attempts).mockResolvedValue([])
    vi.mocked(paymentApi.sandboxVoid).mockResolvedValue({
      id: '00000000-0000-4000-8000-000000000004',
      source_attempt_id: '00000000-0000-4000-8000-000000000002',
      provider: 'authorize_net',
      operation: 'void',
      amount: '1.00000000',
      currency: 'USD',
      status: 'succeeded',
      safe_error_code: null,
      completed_at: null,
      created_at: null,
    })
    vi.mocked(paymentApi.sandboxRefund).mockResolvedValue({
      id: '00000000-0000-4000-8000-000000000005',
      source_attempt_id: '00000000-0000-4000-8000-000000000002',
      provider: 'authorize_net',
      operation: 'refund',
      amount: '0.50000000',
      currency: 'USD',
      status: 'succeeded',
      safe_error_code: null,
      completed_at: null,
      created_at: null,
    })

    const store = usePaymentStore()

    expect(await store.sandboxVoid('account-public-id', 'source-attempt-public-id')).toBe(true)
    expect(paymentApi.sandboxVoid).toHaveBeenCalledWith(
      'account-public-id',
      'source-attempt-public-id',
      '00000000-0000-4000-8000-000000000003',
    )

    expect(await store.sandboxRefund('account-public-id', 'source-attempt-public-id', 50)).toBe(
      true,
    )
    expect(paymentApi.sandboxRefund).toHaveBeenCalledWith(
      'account-public-id',
      'source-attempt-public-id',
      50,
      '00000000-0000-4000-8000-000000000003',
    )
  })

  it('stores only the safe customer profile projection', async () => {
    vi.spyOn(crypto, 'randomUUID').mockReturnValue('00000000-0000-4000-8000-000000000006')
    vi.mocked(paymentApi.attempts).mockResolvedValue([])
    vi.mocked(paymentApi.createSandboxCustomerProfile).mockResolvedValue({
      attempt: {
        id: '00000000-0000-4000-8000-000000000007',
        source_attempt_id: '00000000-0000-4000-8000-000000000002',
        provider: 'authorize_net',
        operation: 'attach_payment_method',
        amount: null,
        currency: null,
        status: 'succeeded',
        safe_error_code: null,
        completed_at: null,
        created_at: null,
      },
      profile: {
        id: '00000000-0000-4000-8000-000000000008',
        provider: 'authorize_net',
        status: 'active',
        masked_account: 'XXXX1111',
        account_type: 'Visa',
        created_at: null,
      },
    })

    const store = usePaymentStore()

    expect(
      await store.createSandboxCustomerProfile('account-public-id', 'source-attempt-public-id'),
    ).toBe(true)
    expect(paymentApi.createSandboxCustomerProfile).toHaveBeenCalledWith(
      'account-public-id',
      'source-attempt-public-id',
      '00000000-0000-4000-8000-000000000006',
    )
    expect(store.customerProfile).toEqual(
      expect.objectContaining({ masked_account: 'XXXX1111', account_type: 'Visa' }),
    )
  })
})
