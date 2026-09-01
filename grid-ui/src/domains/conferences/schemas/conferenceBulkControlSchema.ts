import { z } from 'zod'

export const conferenceBulkControlSchema = z.object({
  action: z.enum(['mute', 'unmute', 'deaf', 'undeaf']),
  expected_participant_count: z.number().int().positive().max(10_000),
  expected_target_count: z.number().int().positive().max(10_000),
  confirmation: z.literal(true),
})

export type ConferenceBulkControlInput = z.infer<typeof conferenceBulkControlSchema>
