import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/shared/api/http'
import { paymentApi } from './paymentApi'
import type { PaymentAttempt } from '../types/payment'

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
  completed_at: null,
  created_at: null,
})

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
})
