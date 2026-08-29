import { z } from 'zod'

const voicemailNotificationCallbackSchema = z
  .object({
    disabled: z.boolean(),
    number: z.string().trim().min(1).max(64).nullable(),
    attempts: z.number().int().min(0).max(100).nullable(),
    interval_s: z.number().int().min(0).max(604800).nullable(),
    timeout_s: z.number().int().min(0).max(3600).nullable(),
    schedule: z.array(z.number().int().min(0).max(604800)).max(100),
  })
  .strict()

const voicemailBoxBaseSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a mailbox name.').max(128),
    mailbox: z.string().regex(/^\d{1,30}$/, 'Use 1–30 digits.'),
    assigned_extension_id: z.uuid().nullable(),
    timezone: z.string().trim().min(5).max(32).nullable(),
    notification_emails: z
      .array(z.email().max(254))
      .max(10)
      .refine(
        (emails) => new Set(emails).size === emails.length,
        'Use each notification email once.',
      ),
    transcribe: z.boolean(),
    require_pin: z.boolean(),
    pin: z
      .string()
      .regex(/^\d{4,6}$/, 'Use a 4–6 digit mailbox PIN.')
      .nullable(),
    check_if_owner: z.boolean(),
    delete_after_notify: z.boolean(),
    include_message_on_notify: z.boolean(),
    include_transcription_on_notify: z.boolean(),
    media_extension: z.enum(['mp3', 'mp4', 'wav']),
    not_configurable: z.boolean(),
    oldest_message_first: z.boolean(),
    save_after_notify: z.boolean(),
    skip_envelope: z.boolean(),
    skip_greeting: z.boolean(),
    skip_instructions: z.boolean(),
    is_voicemail_ff_rw_enabled: z.boolean(),
    seek_duration_ms: z.number().int().min(0).max(300000),
    notify_callback: voicemailNotificationCallbackSchema.nullable(),
  })
  .strict()

export function voicemailBoxFormSchemaFor(editing: boolean, pinConfigured = false) {
  return voicemailBoxBaseSchema.superRefine((input, context) => {
    if (input.require_pin && input.pin === null && (!editing || !pinConfigured)) {
      context.addIssue({
        code: 'custom',
        path: ['pin'],
        message: 'Enter a mailbox PIN when PIN protection is enabled.',
      })
    }

    if (input.save_after_notify && input.delete_after_notify) {
      context.addIssue({
        code: 'custom',
        path: ['delete_after_notify'],
        message: 'Delete after notification cannot be enabled while save is enabled.',
      })
    }

    if (
      input.notify_callback !== null &&
      !input.notify_callback.disabled &&
      input.notify_callback.number === null
    ) {
      context.addIssue({
        code: 'custom',
        path: ['notify_callback', 'number'],
        message: 'Enter a callback number when callback notifications are enabled.',
      })
    }
  })
}

export const voicemailBoxFormSchema = voicemailBoxFormSchemaFor(false)
