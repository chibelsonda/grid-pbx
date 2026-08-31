import { z } from 'zod'

const nonnegativeInteger = z.number().int().nonnegative()

const callActivityTotalsSchema = z
  .object({
    total: nonnegativeInteger,
    inbound: nonnegativeInteger,
    outbound: nonnegativeInteger,
    answered: nonnegativeInteger,
    missed: nonnegativeInteger,
    answer_rate: z.number().min(0).max(100),
    average_duration_seconds: nonnegativeInteger,
  })
  .strict()

export const callActivityRangeSchema = z.enum(['today', '7d', '30d'])

export const callActivityTrendSchema = z
  .object({
    range: callActivityRangeSchema,
    granularity: z.enum(['hour', 'day']),
    timezone: z.string().min(1),
    from: z.iso.datetime({ offset: true }),
    to: z.iso.datetime({ offset: true }),
    totals: callActivityTotalsSchema,
    series: z.array(
      z
        .object({
          start_at: z.iso.datetime({ offset: true }),
          end_at: z.iso.datetime({ offset: true }),
          total: nonnegativeInteger,
          inbound: nonnegativeInteger,
          outbound: nonnegativeInteger,
          answered: nonnegativeInteger,
          missed: nonnegativeInteger,
        })
        .strict(),
    ),
  })
  .strict()

export type CallActivityRange = z.infer<typeof callActivityRangeSchema>
export type CallActivityTrend = z.infer<typeof callActivityTrendSchema>
export type CallActivityPoint = CallActivityTrend['series'][number]
