import { z } from 'zod'

const e164Number = z
  .string()
  .regex(/^\+[1-9]\d{6,14}$/, 'Use E.164 format, for example +15550001000.')

export const blacklistFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a blacklist name.').max(128),
    should_block_anonymous: z.boolean(),
    is_active: z.boolean(),
    numbers: z.array(e164Number).max(10_000, 'A blacklist can contain at most 10,000 numbers.'),
  })
  .strict()
  .refine((value) => new Set(value.numbers).size === value.numbers.length, {
    path: ['numbers'],
    message: 'Each blocked number may appear only once.',
  })
