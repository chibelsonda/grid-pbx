import { z } from 'zod'

const nullableString = (maximum: number) => z.string().trim().max(maximum).nullable()
const notificationEmailsSchema = z
  .array(z.email().max(254))
  .max(10)
  .refine((emails) => new Set(emails).size === emails.length, 'Use each notification email once.')

const voicemailSchema = z
  .object({
    enabled: z.boolean(),
    notification_emails: notificationEmailsSchema,
    transcribe: z.boolean(),
    require_pin: z.boolean(),
    pin: z
      .string()
      .regex(/^\d{4,6}$/, 'Use a 4–6 digit mailbox PIN.')
      .nullable(),
  })
  .strict()

const userFields = {
  first_name: z.string().trim().min(1, 'Enter a first name.').max(128),
  last_name: z.string().trim().min(1, 'Enter a last name.').max(128),
  extension: z.string().regex(/^\d{2,15}$/, 'Use 2–15 digits.'),
  username: z
    .string()
    .trim()
    .max(256)
    .regex(/^[+@.\w_-]+$/, 'Use only letters, numbers, +, @, periods, underscores, and hyphens.')
    .nullable(),
  email: z.email().max(254).nullable(),
  timezone: nullableString(255),
  is_enabled: z.boolean(),
  language: nullableString(32),
  presence_id: nullableString(255),
  call_waiting: z.object({ enabled: z.boolean() }).strict(),
  do_not_disturb: z.object({ enabled: z.boolean() }).strict(),
  contact_list: z.object({ exclude: z.boolean() }).strict(),
  caller_id_options: z
    .object({ outbound_privacy: z.enum(['full', 'name', 'number', 'none']) })
    .strict(),
  voicemail: voicemailSchema,
}

function requireNewMailboxPin(
  input: { voicemail: { enabled: boolean; require_pin: boolean; pin: string | null } },
  context: z.RefinementCtx,
): void {
  if (input.voicemail.enabled && input.voicemail.require_pin && input.voicemail.pin === null) {
    context.addIssue({
      code: 'custom',
      path: ['voicemail', 'pin'],
      message: 'Enter a mailbox PIN when PIN protection is enabled.',
    })
  }
}

export const extensionCreateSchema = z
  .object({
    ...userFields,
    device: z
      .object({
        enabled: z.boolean(),
        name: nullableString(255),
        device_type: z
          .enum([
            'sip_device',
            'cellphone',
            'smartphone',
            'softphone',
            'landline',
            'fax',
            'ata',
            'sip_uri',
          ])
          .nullable(),
        make: nullableString(255),
        model: nullableString(255),
        mac_address: z
          .string()
          .trim()
          .max(64)
          .regex(/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/, 'Enter a valid MAC address.')
          .nullable(),
        sip_username: z.string().trim().min(2).max(32).nullable(),
        sip_password: z.string().min(12).max(32).nullable(),
      })
      .strict(),
  })
  .strict()
  .superRefine((input, context) => {
    requireNewMailboxPin(input, context)

    if (input.device.enabled && input.device.name === null) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'name'],
        message: 'Enter a device name.',
      })
    }

    if (input.device.enabled && input.device.device_type === null) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'device_type'],
        message: 'Select a device type.',
      })
    }
  })

export const extensionUpdateSchema = z.object(userFields).strict()
