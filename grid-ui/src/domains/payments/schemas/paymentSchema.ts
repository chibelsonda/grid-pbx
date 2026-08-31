import { z } from 'zod'

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
  completed_at: z.string().nullable(),
  created_at: z.string().nullable(),
})

export const paymentCustomerProfileSchema = z.object({
  id: z.string().uuid(),
  provider: z.string(),
  status: z.string(),
  masked_account: z.string().nullable(),
  account_type: z.string().nullable(),
  created_at: z.string().nullable(),
})

export const paymentProfileOutcomeSchema = z.object({
  attempt: paymentAttemptSchema,
  profile: paymentCustomerProfileSchema.nullable(),
})
