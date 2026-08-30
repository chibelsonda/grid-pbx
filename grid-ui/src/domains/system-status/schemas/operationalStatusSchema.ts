import { z } from 'zod'

export const operationalStatusSchema = z
  .object({
    observed_at: z.iso.datetime({ offset: true }),
    presence: z
      .object({
        subscription_diagnostics_available: z.boolean(),
        live_status_available: z.literal(false),
        commands_available: z.literal(false),
      })
      .strict(),
    parking: z
      .object({
        summary_available: z.boolean(),
        active_call_count: z.number().int().nonnegative().nullable(),
        actions_available: z.literal(false),
      })
      .strict(),
  })
  .strict()

export type OperationalStatus = z.infer<typeof operationalStatusSchema>
