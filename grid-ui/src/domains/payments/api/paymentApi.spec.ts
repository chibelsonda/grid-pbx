import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/shared/api/http'
import { paymentApi } from './paymentApi'
import type {
  PaymentAttempt,
  PaymentAttemptDetail,
  PaymentCustomerProfile,
  PaymentWebhookDelivery,
} from '../types/payment'

const sourceAttemptId = '00000000-0000-4000-8000-000000000001'
const mutationAttempt = (operation: PaymentAttempt['operation']): PaymentAttempt => ({
  id: '00000000-0000-4000-8000-000000000002',
  source_attempt_id: sourceAttemptId,
  provider: 'authorize_net',
  operation,
  amount: operation === 'attach_payment_method' ? null : '1.00000000',
  currency: operation === 'attach_payment_method' ? null : 'USD',
  status: 'succeeded',
  safe_error_code: null,
  provider_status: null,
  reconciled_at: null,
  completed_at: null,
  created_at: null,
})

const failedDelivery: PaymentWebhookDelivery = {
  id: '00000000-0000-4000-8000-000000000010',
  payment_attempt_id: sourceAttemptId,
  provider: 'authorize_net',
  event_type: 'net.authorize.payment.authcapture.created',
  status: 'failed',
  processing_attempts: 5,
  safe_error_code: 'reconciliation_exhausted',
  can_retry: true,
  recovery_guidance: 'Automatic retries were exhausted. Verify provider connectivity, then retry.',
  event_occurred_at: null,
  received_at: null,
  processed_at: null,
}

describe('payment API', () => {
  afterEach(() => vi.restoreAllMocks())

  it('submits reversal requests without provider references or card data', async () => {
    const post = vi
      .spyOn(http, 'post')
      .mockResolvedValueOnce({ data: { data: mutationAttempt('void') } })
      .mockResolvedValueOnce({ data: { data: mutationAttempt('refund') } })

    await paymentApi.sandboxVoid('account-id', sourceAttemptId, 'void-idempotency-key')
    await paymentApi.sandboxRefund('account-id', sourceAttemptId, 50, 'refund-idempotency-key')

    expect(post).toHaveBeenNthCalledWith(
      1,
      `/api/v1/accounts/account-id/payments/attempts/${sourceAttemptId}/sandbox-void`,
      { confirmation: true },
      { headers: { 'Idempotency-Key': 'void-idempotency-key' } },
    )
    expect(post).toHaveBeenNthCalledWith(
      2,
      `/api/v1/accounts/account-id/payments/attempts/${sourceAttemptId}/sandbox-refunds`,
      { amount_minor: 50, currency: 'USD', confirmation: true },
      { headers: { 'Idempotency-Key': 'refund-idempotency-key' } },
    )
    expect(JSON.stringify(post.mock.calls)).not.toMatch(
      /card|cvv|provider_reference|transaction_id/i,
    )
  })

  it('creates a customer profile from only the public source attempt id', async () => {
    const attempt = mutationAttempt('attach_payment_method')
    const post = vi.spyOn(http, 'post').mockResolvedValue({
      data: {
        data: {
          attempt,
          profile: {
            id: '00000000-0000-4000-8000-000000000003',
            provider: 'authorize_net',
            status: 'active',
            masked_account: 'XXXX1111',
            account_type: 'Visa',
            created_at: null,
            updated_at: null,
          },
        },
      },
    })

    const outcome = await paymentApi.createSandboxCustomerProfile(
      'account-id',
      sourceAttemptId,
      'profile-idempotency-key',
    )

    expect(post).toHaveBeenCalledWith(
      `/api/v1/accounts/account-id/payments/attempts/${sourceAttemptId}/sandbox-customer-profile`,
      { confirmation: true },
      { headers: { 'Idempotency-Key': 'profile-idempotency-key' } },
    )
    expect(outcome.profile?.masked_account).toBe('XXXX1111')
    expect(JSON.stringify(outcome)).not.toMatch(/customer_profile_id|payment_profile_id/i)
  })

  it('loads only the safe account-scoped payment profile inventory', async () => {
    const profile: PaymentCustomerProfile = {
      id: '00000000-0000-4000-8000-000000000003',
      provider: 'authorize_net',
      status: 'active',
      masked_account: 'XXXX1111',
      account_type: 'Visa',
      created_at: '2026-08-31T02:00:00+00:00',
      updated_at: '2026-08-31T02:00:00+00:00',
    }
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: [profile] } })

    const result = await paymentApi.profiles('account-id')

    expect(get).toHaveBeenCalledWith('/api/v1/accounts/account-id/payments/customer-profiles')
    expect(result).toEqual([profile])
    expect(JSON.stringify(result)).not.toMatch(
      /customer_profile_id|payment_profile_id|provider_reference|hash/i,
    )
  })

  it('loads sanitized webhook health and retries by public delivery id only', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({
      data: {
        data: {
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
          deliveries: [failedDelivery],
        },
      },
    })
    const post = vi.spyOn(http, 'post').mockResolvedValue({ data: { data: failedDelivery } })

    const health = await paymentApi.webhookHealth('account-id')
    await paymentApi.retryWebhook('account-id', failedDelivery.id)

    expect(get).toHaveBeenCalledWith('/api/v1/accounts/account-id/payments/webhook-deliveries')
    expect(post).toHaveBeenCalledWith(
      `/api/v1/accounts/account-id/payments/webhook-deliveries/${failedDelivery.id}/retry`,
      {},
    )
    expect(health.summary.requiring_attention).toBe(1)
    expect(JSON.stringify([health, post.mock.calls])).not.toMatch(
      /provider_reference|transaction_key|signature/i,
    )
  })

  it('loads a sanitized immutable event timeline by public attempt id', async () => {
    const detail: PaymentAttemptDetail = {
      ...mutationAttempt('charge'),
      source_attempt_id: null,
      events: [
        {
          id: '00000000-0000-4000-8000-000000000011',
          event_type: 'provider_result_recorded',
          status: 'succeeded',
          summary: 'The provider result was recorded.',
          safe_error_code: null,
          provider_status: null,
          created_at: '2026-08-31T02:00:00+00:00',
        },
      ],
    }
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: detail } })

    const result = await paymentApi.attempt('account-id', sourceAttemptId)

    expect(get).toHaveBeenCalledWith(
      `/api/v1/accounts/account-id/payments/attempts/${sourceAttemptId}`,
    )
    expect(result.events[0]?.summary).toBe('The provider result was recorded.')
    expect(JSON.stringify(result)).not.toMatch(
      /provider_reference|request_ip|signature|safe_context/i,
    )
  })
})
