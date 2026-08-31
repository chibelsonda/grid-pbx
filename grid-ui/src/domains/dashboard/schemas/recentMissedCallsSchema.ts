import { z } from 'zod'
import { callActivityRangeSchema } from './callActivityTrendSchema'

const partySchema = z
  .object({
    name: z.string().nullable(),
    number: z.string().nullable(),
  })
  .strict()

export const recentMissedCallsSchema = z
  .object({
    generated_at: z.iso.datetime({ offset: true }),
    data_as_of: z.iso.datetime({ offset: true }).nullable(),
    range: callActivityRangeSchema,
    timezone: z.string().min(1),
    from: z.iso.datetime({ offset: true }),
    to: z.iso.datetime({ offset: true }),
    total: z.number().int().nonnegative(),
    items: z
      .array(
        z
          .object({
            id: z.uuid(),
            started_at: z.iso.datetime({ offset: true }),
            caller: partySchema,
            destination: partySchema,
            duration_seconds: z.number().int().nonnegative(),
            hangup_cause: z.string().nullable(),
          })
          .strict(),
      )
      .max(5),
  })
  .strict()

export type RecentMissedCalls = z.infer<typeof recentMissedCallsSchema>
export type RecentMissedCall = RecentMissedCalls['items'][number]
