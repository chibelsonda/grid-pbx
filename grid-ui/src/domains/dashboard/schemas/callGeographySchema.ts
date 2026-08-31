import { z } from 'zod'
import { callActivityRangeSchema } from './callActivityTrendSchema'

const nonnegativeInteger = z.number().int().nonnegative()

export const callGeographySchema = z
  .object({
    generated_at: z.iso.datetime({ offset: true }),
    data_as_of: z.iso.datetime({ offset: true }).nullable(),
    range: callActivityRangeSchema,
    timezone: z.string().min(1),
    from: z.iso.datetime({ offset: true }),
    to: z.iso.datetime({ offset: true }),
    status: z.enum(['unavailable', 'empty', 'ready']),
    capability: z
      .object({
        available: z.boolean(),
        source: z.string().min(1).nullable(),
        reason: z.string().min(1).nullable(),
      })
      .strict(),
    coverage: z
      .object({
        total_calls: nonnegativeInteger,
        located_calls: nonnegativeInteger,
        percentage: z.number().min(0).max(100),
      })
      .strict(),
    locations: z.array(
      z
        .object({
          key: z.string().min(1).max(64),
          label: z.string().min(1).max(255),
          locality: z.string().max(255).nullable(),
          region_code: z.string().max(32).nullable(),
          country_code: z.string().length(2),
          latitude: z.number().min(-90).max(90),
          longitude: z.number().min(-180).max(180),
          precision: z.string().min(1).max(24),
          total: nonnegativeInteger,
          inbound: nonnegativeInteger,
          outbound: nonnegativeInteger,
        })
        .strict(),
    ),
    disclosure: z.string().min(1),
  })
  .strict()
  .refine((value) => value.coverage.located_calls <= value.coverage.total_calls, {
    path: ['coverage', 'located_calls'],
    message: 'Located calls cannot exceed total calls.',
  })

export type CallGeography = z.infer<typeof callGeographySchema>
export type CallGeographyLocation = CallGeography['locations'][number]
