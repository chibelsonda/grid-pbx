import { z } from 'zod'
import { isSafeSwitchRegex } from '@/shared/forms/safeSwitchRegex'

const optionalText = (maximum: number) =>
  z.preprocess(
    (value) => (typeof value === 'string' && value.trim() === '' ? null : value),
    z.string().trim().max(maximum).nullable(),
  )

const entrySchema = z
  .object({
    id: z.string().uuid().nullable(),
    display_name: optionalText(128),
    number: z.preprocess(
      (value) => (typeof value === 'string' && value.trim() === '' ? null : value),
      z
        .string()
        .trim()
        .regex(/^\+?[0-9]{1,32}$/, 'Use an optional + followed by up to 32 digits.')
        .nullable(),
    ),
    pattern: z.preprocess(
      (value) => (typeof value === 'string' && value.trim() === '' ? null : value),
      z
        .string()
        .trim()
        .max(512)
        .refine(isSafeSwitchRegex, 'Enter a supported regular expression.')
        .nullable(),
    ),
  })
  .strict()
  .superRefine((entry, context) => {
    if ((entry.number === null) === (entry.pattern === null)) {
      context.addIssue({
        code: 'custom',
        path: ['number'],
        message: 'Enter either a number/prefix or a pattern.',
      })
    }
  })

export const callerIdListFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a Caller-ID List name.').max(128),
    description: optionalText(128),
    organization: optionalText(255),
    entries: z
      .array(entrySchema)
      .max(10_000, 'A Caller-ID List can contain at most 10,000 entries.'),
  })
  .strict()
