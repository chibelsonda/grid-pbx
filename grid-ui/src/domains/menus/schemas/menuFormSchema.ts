import { z } from 'zod'

const nullableUuid = z.uuid().nullable()
const nullablePattern = z.string().max(256).nullable()

export const menuFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a menu name.').max(128),
    timeout: z.number().int().min(1).max(60_000),
    interdigit_timeout: z.number().int().min(1).max(10_000),
    max_extension_length: z.number().int().min(1).max(6),
    retries: z.number().int().min(1).max(10),
    hunt: z.boolean(),
    allow_record_from_offnet: z.boolean(),
    suppress_media: z.boolean(),
    record_pin: z
      .string()
      .regex(/^\d{3,6}$/, 'Enter a 3–6 digit recording PIN.')
      .nullable(),
    clear_record_pin: z.boolean(),
    hunt_allow: nullablePattern,
    hunt_deny: nullablePattern,
    greeting_media_id: nullableUuid,
    clear_greeting_media: z.boolean(),
    invalid_media_enabled: z.boolean(),
    invalid_media_id: nullableUuid,
    clear_invalid_media: z.boolean(),
    transfer_media_enabled: z.boolean(),
    transfer_media_id: nullableUuid,
    clear_transfer_media: z.boolean(),
    exit_media_enabled: z.boolean(),
    exit_media_id: nullableUuid,
    clear_exit_media: z.boolean(),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.record_pin !== null && value.clear_record_pin) {
      context.addIssue({
        code: 'custom',
        path: ['record_pin'],
        message: 'Enter a replacement PIN or remove the current PIN, not both.',
      })
    }

    for (const type of ['greeting', 'invalid', 'transfer', 'exit'] as const) {
      if (value[`${type}_media_id`] !== null && value[`clear_${type}_media`]) {
        context.addIssue({
          code: 'custom',
          path: [`${type}_media_id`],
          message: 'Select a prompt or clear the current prompt, not both.',
        })
      }
    }
  })
