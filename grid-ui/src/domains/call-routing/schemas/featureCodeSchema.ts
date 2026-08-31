import { z } from 'zod'

const projectionStatusSchema = z.enum(['healthy', 'syncing', 'stale', 'error'])

export const featureCodeRouteSchema = z.object({
  id: z.string().uuid(),
  numbers: z.array(z.string()),
  patterns: z.array(z.string()),
  root_module: z.string().nullable(),
  feature_code: z.object({
    name: z.string().nullable(),
    number: z.string().nullable(),
  }),
  sync_status: projectionStatusSchema,
  last_synced_at: z.string().nullable(),
})

export const featureCodePageSchema = z.object({
  data: z.array(featureCodeRouteSchema),
  meta: z.object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive(),
    total: z.number().int().nonnegative(),
    sync: z.object({
      status: projectionStatusSchema,
      last_successful_at: z.string().nullable(),
      error_message: z.string().nullable(),
      scope: z.literal('pbx_projection'),
    }),
  }),
})
