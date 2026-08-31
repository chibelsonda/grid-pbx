import { z } from 'zod'
import { callActivityRangeSchema } from './callActivityTrendSchema'

const nonnegativeInteger = z.number().int().nonnegative()

export const topCallDestinationSchema = z
  .object({
    name: z.string().nullable(),
    number: z.string().nullable(),
    total: nonnegativeInteger,
    inbound: nonnegativeInteger,
    outbound: nonnegativeInteger,
    answered: nonnegativeInteger,
    unanswered: nonnegativeInteger,
  })
  .strict()

export const topCallDestinationsSchema = z
  .object({
    generated_at: z.iso.datetime({ offset: true }),
    data_as_of: z.iso.datetime({ offset: true }).nullable(),
    range: callActivityRangeSchema,
    timezone: z.string().min(1),
    from: z.iso.datetime({ offset: true }),
    to: z.iso.datetime({ offset: true }),
    destinations: z.array(topCallDestinationSchema).max(5),
  })
  .strict()

export type TopCallDestination = z.infer<typeof topCallDestinationSchema>
export type TopCallDestinations = z.infer<typeof topCallDestinationsSchema>
