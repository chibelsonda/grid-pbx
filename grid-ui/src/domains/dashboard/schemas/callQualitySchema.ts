import { z } from 'zod'
import { callActivityRangeSchema } from './callActivityTrendSchema'

const nonnegativeInteger = z.number().int().nonnegative()

const durationBandSchema = z
  .object({
    key: z.enum(['under_30', '30_to_59', '1_to_5_minutes', '5_to_15_minutes', '15_minutes_plus']),
    label: z.string().min(1),
    minimum_seconds: nonnegativeInteger,
    maximum_seconds: nonnegativeInteger.nullable(),
    count: nonnegativeInteger,
    percentage: z.number().min(0).max(100),
  })
  .strict()

export const callQualitySchema = z
  .object({
    generated_at: z.iso.datetime({ offset: true }),
    data_as_of: z.iso.datetime({ offset: true }).nullable(),
    range: callActivityRangeSchema,
    timezone: z.string().min(1),
    from: z.iso.datetime({ offset: true }),
    to: z.iso.datetime({ offset: true }),
    answer_time: z
      .object({
        answered_inbound_calls: nonnegativeInteger,
        average_pre_answer_seconds: nonnegativeInteger.nullable(),
        disclosure: z.string().min(1),
      })
      .strict(),
    potential_abandonment: z
      .object({
        threshold_seconds: nonnegativeInteger,
        inbound_calls: nonnegativeInteger,
        unanswered_inbound_calls: nonnegativeInteger,
        potential_calls: nonnegativeInteger,
        rate: z.number().min(0).max(100),
        disclosure: z.string().min(1),
      })
      .strict(),
    duration_distribution: z
      .object({
        total_calls: nonnegativeInteger,
        bands: z.array(durationBandSchema).length(5),
      })
      .strict(),
  })
  .strict()

export type CallQuality = z.infer<typeof callQualitySchema>
export type CallDurationBand = CallQuality['duration_distribution']['bands'][number]
