import { z } from 'zod'

export const voicemailBoxFormSchema = z
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
  })
  .strict()
