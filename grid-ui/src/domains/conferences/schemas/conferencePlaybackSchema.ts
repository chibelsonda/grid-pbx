import { z } from 'zod'

export const conferencePlaybackSchema = z
  .object({
    media_id: z.uuid('Select projected account audio.'),
    participant_id: z.string().min(1).max(4096).nullable(),
    confirmation: z.literal(true),
  })
  .strict()
