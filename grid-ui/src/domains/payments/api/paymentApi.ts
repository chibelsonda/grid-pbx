import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import {
  paymentAttemptSchema,
  paymentAttemptDetailSchema,
  paymentCapabilitySchema,
  paymentCustomerProfileSchema,
  paymentProfileOutcomeSchema,
  paymentWebhookDeliverySchema,
  paymentWebhookHealthSchema,
} from '../schemas/paymentSchema'
import type {
  PaymentAttempt,
  PaymentAttemptDetail,
  PaymentCapability,
  PaymentCustomerProfile,
  PaymentOpaqueData,
  PaymentProfileOutcome,
  PaymentWebhookDelivery,
  PaymentWebhookHealth,
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

  async profiles(accountId: string): Promise<PaymentCustomerProfile[]> {
    return paymentCustomerProfileSchema
      .array()
      .parse(
        unwrapApiData(
          await http.get<ApiResponse<PaymentCustomerProfile[]>>(
            `/api/v1/accounts/${accountId}/payments/customer-profiles`,
          ),
        ),
      )
  },

  async attempt(accountId: string, attemptId: string): Promise<PaymentAttemptDetail> {
    return paymentAttemptDetailSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<PaymentAttemptDetail>>(
          `/api/v1/accounts/${accountId}/payments/attempts/${attemptId}`,
        ),
      ),
    )
  },

  async webhookHealth(accountId: string): Promise<PaymentWebhookHealth> {
    return paymentWebhookHealthSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<PaymentWebhookHealth>>(
          `/api/v1/accounts/${accountId}/payments/webhook-deliveries`,
        ),
      ),
    )
  },

  async retryWebhook(accountId: string, deliveryId: string): Promise<PaymentWebhookDelivery> {
    return paymentWebhookDeliverySchema.parse(
      unwrapApiData(
        await http.post<ApiResponse<PaymentWebhookDelivery>>(
          `/api/v1/accounts/${accountId}/payments/webhook-deliveries/${deliveryId}/retry`,
          {},
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
