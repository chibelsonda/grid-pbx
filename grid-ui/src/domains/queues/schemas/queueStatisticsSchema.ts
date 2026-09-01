import { z } from 'zod'

const metricsSchema = z.object({
  waiting: z.number().int().nonnegative(),
  handled: z.number().int().nonnegative(),
  abandoned: z.number().int().nonnegative(),
  processed: z.number().int().nonnegative(),
  average_wait_seconds: z.number().int().nonnegative().nullable(),
  average_talk_seconds: z.number().int().nonnegative().nullable(),
  longest_current_wait_seconds: z.number().int().nonnegative(),
})

export const queueStatisticsSchema = z.object({
  observed_at: z.string().datetime({ offset: true }),
  totals: metricsSchema,
  queues: z.array(
    metricsSchema.extend({
      id: z.string().uuid(),
      name: z.string(),
    }),
  ),
  unresolved_records: z.number().int().nonnegative(),
})

export type QueueStatistics = z.infer<typeof queueStatisticsSchema>
export type QueueStatisticsMetrics = z.infer<typeof metricsSchema>
