import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { paymentApi } from '../api/paymentApi'
import { usePaymentStore } from './paymentStore'

vi.mock('../api/paymentApi', () => ({
  paymentApi: {
    capabilities: vi.fn(),
    attempts: vi.fn(),
    attempt: vi.fn(),
    profiles: vi.fn(),
    webhookHealth: vi.fn(),
    retryWebhook: vi.fn(),
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
      provider_status: null,
      reconciled_at: null,
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
      provider_status: null,
      reconciled_at: null,
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
      provider_status: null,
      reconciled_at: null,
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
        provider_status: null,
        reconciled_at: null,
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
        updated_at: null,
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
    expect(store.customerProfiles).toEqual([
      expect.objectContaining({ masked_account: 'XXXX1111', account_type: 'Visa' }),
    ])
  })

  it('hydrates saved profiles after reload using only the safe inventory', async () => {
    vi.mocked(paymentApi.profiles).mockResolvedValue([
      {
        id: '00000000-0000-4000-8000-000000000008',
        provider: 'authorize_net',
        status: 'active',
        masked_account: 'XXXX1111',
        account_type: 'Visa',
        created_at: null,
        updated_at: null,
      },
    ])
    const store = usePaymentStore()

    await store.loadProfiles('account-public-id')

    expect(paymentApi.profiles).toHaveBeenCalledWith('account-public-id')
    expect(store.customerProfiles).toHaveLength(1)
    expect(JSON.stringify(store.customerProfiles)).not.toMatch(
      /customer_profile_id|payment_profile_id|provider_reference|hash/i,
    )
  })

  it('refreshes sanitized webhook health after requesting a bounded retry', async () => {
    const delivery = {
      id: '00000000-0000-4000-8000-000000000010',
      payment_attempt_id: '00000000-0000-4000-8000-000000000002',
      provider: 'authorize_net',
      event_type: 'net.authorize.payment.authcapture.created',
      status: 'failed' as const,
      processing_attempts: 5,
      safe_error_code: 'reconciliation_exhausted',
      can_retry: true,
      recovery_guidance:
        'Automatic retries were exhausted. Verify provider connectivity, then retry.',
      event_occurred_at: null,
      received_at: null,
      processed_at: null,
    }
    const health = {
      summary: {
        received: 0,
        processing: 0,
        processed: 0,
        ignored: 0,
        retry_pending: 0,
        failed: 1,
        total: 1,
        requiring_attention: 1,
      },
      recovery_available: true,
      deliveries: [delivery],
    }
    vi.mocked(paymentApi.webhookHealth).mockResolvedValue(health)
    vi.mocked(paymentApi.retryWebhook).mockResolvedValue({
      ...delivery,
      status: 'received',
      safe_error_code: null,
      can_retry: false,
      recovery_guidance: 'Reconciliation is queued.',
    })
    vi.mocked(paymentApi.attempts).mockResolvedValue([])
    const store = usePaymentStore()
    store.webhookHealth = health

    expect(await store.retryWebhook('account-public-id', delivery.id)).toBe(true)

    expect(paymentApi.retryWebhook).toHaveBeenCalledWith('account-public-id', delivery.id)
    expect(paymentApi.webhookHealth).toHaveBeenCalledWith('account-public-id')
    expect(store.recoveringWebhookId).toBeNull()
  })

  it('stores a sanitized attempt timeline under its public id', async () => {
    const attemptId = '00000000-0000-4000-8000-000000000002'
    vi.mocked(paymentApi.attempt).mockResolvedValue({
      id: attemptId,
      source_attempt_id: null,
      provider: 'authorize_net',
      operation: 'charge',
      amount: '1.00000000',
      currency: 'USD',
      status: 'succeeded',
      safe_error_code: null,
      provider_status: null,
      reconciled_at: null,
      completed_at: null,
      created_at: null,
      events: [
        {
          id: '00000000-0000-4000-8000-000000000011',
          event_type: 'provider_result_recorded',
          status: 'succeeded',
          summary: 'The provider result was recorded.',
          safe_error_code: null,
          provider_status: null,
          created_at: null,
        },
      ],
    })
    const store = usePaymentStore()

    expect(await store.loadAttempt('account-public-id', attemptId)).toBe(true)

    expect(paymentApi.attempt).toHaveBeenCalledWith('account-public-id', attemptId)
    expect(store.attemptDetails[attemptId]?.events).toHaveLength(1)
    expect(store.loadingAttemptId).toBeNull()
  })
})
