import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import {
  paymentAttemptSchema,
  paymentCapabilitySchema,
  paymentProfileOutcomeSchema,
} from '../schemas/paymentSchema'
import type {
  PaymentAttempt,
  PaymentCapability,
  PaymentOpaqueData,
  PaymentProfileOutcome,
} from '../types/payment'

export const paymentApi = {
  async capabilities(accountId: string): Promise<PaymentCapability> {
    return paymentCapabilitySchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<PaymentCapability>>(
          `/api/v1/accounts/${accountId}/payments/capabilities`,
        ),
      ),
    )
  },

  async sandboxCharge(
    accountId: string,
    amountMinor: number,
    opaqueData: PaymentOpaqueData,
    idempotencyKey: string,
  ): Promise<PaymentAttempt> {
    return paymentAttemptSchema.parse(
      unwrapApiData(
        await http.post<ApiResponse<PaymentAttempt>>(
          `/api/v1/accounts/${accountId}/payments/sandbox-charges`,
          {
            amount_minor: amountMinor,
            currency: 'USD',
            confirmation: true,
            opaque_data: opaqueData,
          },
          { headers: { 'Idempotency-Key': idempotencyKey } },
        ),
      ),
    )
  },

  async attempts(accountId: string): Promise<PaymentAttempt[]> {
    return paymentAttemptSchema
      .array()
      .parse(
        unwrapApiData(
          await http.get<ApiResponse<PaymentAttempt[]>>(
            `/api/v1/accounts/${accountId}/payments/attempts`,
          ),
        ),
      )
  },

  async sandboxVoid(
    accountId: string,
    sourceAttemptId: string,
    idempotencyKey: string,
  ): Promise<PaymentAttempt> {
    return paymentAttemptSchema.parse(
      unwrapApiData(
        await http.post<ApiResponse<PaymentAttempt>>(
          `/api/v1/accounts/${accountId}/payments/attempts/${sourceAttemptId}/sandbox-void`,
          { confirmation: true },
          { headers: { 'Idempotency-Key': idempotencyKey } },
        ),
      ),
    )
  },

  async sandboxRefund(
    accountId: string,
    sourceAttemptId: string,
    amountMinor: number,
    idempotencyKey: string,
  ): Promise<PaymentAttempt> {
    return paymentAttemptSchema.parse(
      unwrapApiData(
        await http.post<ApiResponse<PaymentAttempt>>(
          `/api/v1/accounts/${accountId}/payments/attempts/${sourceAttemptId}/sandbox-refunds`,
          { amount_minor: amountMinor, currency: 'USD', confirmation: true },
          { headers: { 'Idempotency-Key': idempotencyKey } },
        ),
      ),
    )
  },

  async createSandboxCustomerProfile(
    accountId: string,
    sourceAttemptId: string,
    idempotencyKey: string,
  ): Promise<PaymentProfileOutcome> {
    return paymentProfileOutcomeSchema.parse(
      unwrapApiData(
        await http.post<ApiResponse<PaymentProfileOutcome>>(
          `/api/v1/accounts/${accountId}/payments/attempts/${sourceAttemptId}/sandbox-customer-profile`,
          { confirmation: true },
          { headers: { 'Idempotency-Key': idempotencyKey } },
        ),
      ),
    )
  },
}
