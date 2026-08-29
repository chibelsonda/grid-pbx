import { z } from 'zod'

const digitList = z
  .array(z.string().regex(/^\d+$/, 'Use digits only.').max(32))
  .max(20)
  .refine((values) => new Set(values).size === values.length, 'Enter each access number once.')

const nullableText = (maximum: number) => z.string().max(maximum).nullable()
const nullablePin = z.string().regex(/^\d{1,32}$/, 'Use 1–32 digits.').nullable()

export const conferenceFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a conference name.').max(128),
    owner_id: z.uuid().nullable(),
    conference_numbers: digitList,
    member_numbers: digitList,
    moderator_numbers: digitList,
    member_pin: nullablePin,
    clear_member_pin: z.boolean(),
    moderator_pin: nullablePin,
    clear_moderator_pin: z.boolean(),
    member_join_muted: z.boolean(),
    member_join_deaf: z.boolean(),
    member_play_entry_prompt: z.boolean(),
    moderator_join_muted: z.boolean(),
    moderator_join_deaf: z.boolean(),
    max_participants: z.number().int().min(1).max(10_000).nullable(),
    language: nullableText(16),
    profile_name: nullableText(128),
    caller_controls: nullableText(128),
    moderator_controls: nullableText(128),
    play_name: z.boolean(),
    play_welcome: z.boolean(),
    require_moderator: z.boolean(),
    wait_for_moderator: z.boolean(),
    max_members_media_id: z.uuid().nullable(),
    play_entry_tone_mode: z.enum(['enabled', 'disabled', 'media', 'current_custom']),
    play_entry_tone_media_id: z.uuid().nullable(),
    play_exit_tone_mode: z.enum(['enabled', 'disabled', 'media', 'current_custom']),
    play_exit_tone_media_id: z.uuid().nullable(),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.member_pin !== null && value.clear_member_pin) {
      context.addIssue({
        code: 'custom',
        path: ['member_pin'],
        message: 'A member PIN cannot be replaced and removed together.',
      })
    }

    if (value.moderator_pin !== null && value.clear_moderator_pin) {
      context.addIssue({
        code: 'custom',
        path: ['moderator_pin'],
        message: 'A moderator PIN cannot be replaced and removed together.',
      })
    }

    for (const [modeField, mediaField] of [
      ['play_entry_tone_mode', 'play_entry_tone_media_id'],
      ['play_exit_tone_mode', 'play_exit_tone_media_id'],
    ] as const) {
      if (value[modeField] === 'media' && value[mediaField] === null) {
        context.addIssue({
          code: 'custom',
          path: [mediaField],
          message: 'Select a conference tone.',
        })
      }
    }
  })
