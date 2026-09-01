import { z } from 'zod'

export function createSandboxRefundFormSchema(maximumAmountMinor: number) {
  return z.object({
    amount_minor: z
      .number('Enter a whole refund amount in cents.')
      .int('Enter a whole refund amount in cents.')
      .min(1, 'Enter a whole amount of at least 1 cent.')
      .max(
        maximumAmountMinor,
        `The maximum refundable amount is ${new Intl.NumberFormat(undefined, {
          style: 'currency',
          currency: 'USD',
        }).format(maximumAmountMinor / 100)}.`,
      ),
  })
}

export const paymentCapabilitySchema = z.object({
  enabled: z.boolean(),
  provider: z.string(),
  environment: z.enum(['sandbox', 'unsupported']),
  configured: z.boolean(),
  capture_strategy: z.literal('hosted_or_tokenized'),
  server_accepts_card_data: z.literal(false),
  client: z.object({
    available: z.boolean(),
    accept_ui_url: z.string().url().nullable(),
    api_login_id: z.string().min(1).nullable(),
    public_client_key: z.string().min(1).nullable(),
    sandbox_max_charge_minor: z.number().int().positive().nullable(),
    sandbox_max_refund_minor: z.number().int().positive().nullable(),
  }),
  mutations: z.object({
    attach_payment_method: z.boolean(),
    charge: z.boolean(),
    void: z.boolean(),
    refund: z.boolean(),
  }),
  webhooks: z.object({
    enabled: z.boolean(),
    configured: z.boolean(),
    accepting: z.boolean(),
  }),
})

export const paymentAttemptSchema = z.object({
  id: z.string().uuid(),
  source_attempt_id: z.string().uuid().nullable(),
  provider: z.string(),
  operation: z.enum(['charge', 'refund', 'void', 'attach_payment_method']),
  amount: z.string().nullable(),
  currency: z.literal('USD').nullable(),
  status: z.enum(['pending', 'succeeded', 'failed', 'indeterminate', 'cancelled']),
  safe_error_code: z.string().nullable(),
  provider_status: z.string().nullable(),
  reconciled_at: z.string().nullable(),
  completed_at: z.string().nullable(),
  created_at: z.string().nullable(),
})

export const paymentAttemptEventSchema = z.object({
  id: z.string().uuid(),
  event_type: z.string(),
  status: z.enum(['pending', 'succeeded', 'failed', 'indeterminate', 'cancelled']).nullable(),
  summary: z.string(),
  safe_error_code: z.string().nullable(),
  provider_status: z.string().nullable(),
  created_at: z.string().nullable(),
})

export const paymentAttemptDetailSchema = paymentAttemptSchema.extend({
  events: paymentAttemptEventSchema.array(),
})

export const paymentCustomerProfileSchema = z.object({
  id: z.string().uuid(),
  provider: z.string(),
  status: z.string(),
  masked_account: z.string().nullable(),
  account_type: z.string().nullable(),
  created_at: z.string().nullable(),
  updated_at: z.string().nullable(),
})

export const paymentProfileOutcomeSchema = z.object({
  attempt: paymentAttemptSchema,
  profile: paymentCustomerProfileSchema.nullable(),
})

export const paymentWebhookDeliveryStatusSchema = z.enum([
  'received',
  'processing',
  'processed',
  'ignored',
  'retry_pending',
  'failed',
])

export const paymentWebhookDeliverySchema = z.object({
  id: z.string().uuid(),
  payment_attempt_id: z.string().uuid().nullable(),
  provider: z.string(),
  event_type: z.string(),
  status: paymentWebhookDeliveryStatusSchema,
  processing_attempts: z.number().int().nonnegative(),
  safe_error_code: z.string().nullable(),
  can_retry: z.boolean(),
  recovery_guidance: z.string(),
  event_occurred_at: z.string().nullable(),
  received_at: z.string().nullable(),
  processed_at: z.string().nullable(),
})

const paymentWebhookSummarySchema = z.object({
  received: z.number().int().nonnegative(),
  processing: z.number().int().nonnegative(),
  processed: z.number().int().nonnegative(),
  ignored: z.number().int().nonnegative(),
  retry_pending: z.number().int().nonnegative(),
  failed: z.number().int().nonnegative(),
  total: z.number().int().nonnegative(),
  requiring_attention: z.number().int().nonnegative(),
})

export const paymentWebhookHealthSchema = z.object({
  summary: paymentWebhookSummarySchema,
  recovery_available: z.boolean(),
  deliveries: paymentWebhookDeliverySchema.array(),
})
