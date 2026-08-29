import { z } from 'zod'

const optionalText = (maximum: number) =>
  z
    .string()
    .trim()
    .max(maximum)
    .transform((value) => value || null)

export const accountSettingsSchema = z.object({
  name: z.string().trim().min(1, 'Enter an account name.').max(128),
  organization_name: optionalText(255),
  timezone: optionalText(64),
  language: optionalText(32),
  call_waiting_enabled: z.boolean(),
  do_not_disturb_enabled: z.boolean(),
  outbound_privacy: z.enum(['full', 'name', 'number', 'none']),
  show_rate: z.boolean(),
  ringtone_internal: optionalText(255),
  ringtone_external: optionalText(255),
  caller_id: z.object({
    internal: z.object({ name: optionalText(35), number: optionalText(35) }),
    external: z.object({
      name: optionalText(35),
      phone_number_id: z.string().uuid().nullable(),
      preserve_number: z.boolean(),
    }),
    emergency: z.object({
      name: optionalText(35),
      phone_number_id: z.string().uuid().nullable(),
      preserve_number: z.boolean(),
    }),
  }),
})
