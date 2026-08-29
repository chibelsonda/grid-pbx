import { z } from 'zod'

const groupMemberSchema = z
  .object({
    type: z.enum(['user', 'device', 'group']),
    id: z.uuid(),
    weight: z.number().int().min(1).max(100),
  })
  .strict()

export const groupFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a group name.').max(128),
    music_on_hold_media_id: z.uuid().nullable(),
    members: z
      .array(groupMemberSchema)
      .max(100)
      .refine(
        (members) => new Set(members.map(({ id }) => id)).size === members.length,
        'Select each member once.',
      ),
  })
  .strict()

