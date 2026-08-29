import { z } from 'zod'

const nullableString = (maximum: number) => z.string().trim().max(maximum).nullable()
const starterDeviceTypeSchema = z.enum([
  'sip_device',
  'smartphone',
  'softphone',
  'fax',
  'ata',
])
const provisionableStarterDeviceTypes = new Set(['sip_device', 'fax', 'ata'])
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

const hotdeskSchema = z
  .object({
    enabled: z.boolean(),
    id: z
      .string()
      .trim()
      .regex(/^[0-9+#*]{4,15}$/, 'Use 4–15 dial-pad characters.')
      .nullable(),
    keep_logged_in_elsewhere: z.boolean(),
    require_pin: z.boolean(),
    pin: z
      .string()
      .regex(/^\d{4,15}$/, 'Use a 4–15 digit hotdesk PIN.')
      .nullable(),
    clear_pin: z.boolean(),
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
  password: z.string().min(6, 'Use at least 6 characters.').max(256).nullable(),
  password_confirmation: z.string().max(256).nullable(),
  require_password_update: z.boolean(),
  clear_credentials: z.boolean(),
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
  hotdesk: hotdeskSchema,
  voicemail: voicemailSchema,
}

function validateCredentials(
  input: {
    username: string | null
    password: string | null
    password_confirmation: string | null
    require_password_update: boolean
    clear_credentials: boolean
  },
  context: z.RefinementCtx,
  currentUsername?: string | null,
): void {
  const usernameChanged =
    input.username !== null &&
    (currentUsername === null ||
      (currentUsername !== undefined &&
        input.username.toLocaleLowerCase() !== currentUsername.toLocaleLowerCase()))
  const creatingLogin = currentUsername === null && input.username !== null

  if ((creatingLogin || usernameChanged) && input.password === null) {
    context.addIssue({
      code: 'custom',
      path: ['password'],
      message: 'Enter a password when creating or changing the Switch user login.',
    })
  }

  if (input.password !== null && input.username === null) {
    context.addIssue({
      code: 'custom',
      path: ['username'],
      message: 'Enter a username when setting a Switch user password.',
    })
  }

  if (input.password !== input.password_confirmation) {
    context.addIssue({
      code: 'custom',
      path: ['password_confirmation'],
      message: 'Passwords do not match.',
    })
  }

  if (input.require_password_update && input.username === null) {
    context.addIssue({
      code: 'custom',
      path: ['require_password_update'],
      message: 'Enable login credentials before requiring a password update.',
    })
  }

  if (input.clear_credentials && input.username !== null) {
    context.addIssue({
      code: 'custom',
      path: ['clear_credentials'],
      message: 'A username cannot be retained while removing login credentials.',
    })
  }

  if (currentUsername === null && input.clear_credentials) {
    context.addIssue({
      code: 'custom',
      path: ['clear_credentials'],
      message: 'This user does not have login credentials to remove.',
    })
  }

  if (currentUsername && input.username === null && !input.clear_credentials) {
    context.addIssue({
      code: 'custom',
      path: ['username'],
      message: 'Use Remove login credentials to confirm deletion of this Switch login.',
    })
  }
}

function validateHotdesk(
  input: {
    hotdesk: {
      enabled: boolean
      id: string | null
      require_pin: boolean
      pin: string | null
      clear_pin: boolean
    }
  },
  context: z.RefinementCtx,
  requireNewPin: boolean,
): void {
  if (input.hotdesk.enabled && input.hotdesk.id === null) {
    context.addIssue({
      code: 'custom',
      path: ['hotdesk', 'id'],
      message: 'Enter a hotdesk ID when hotdesking is enabled.',
    })
  }

  if (input.hotdesk.clear_pin && input.hotdesk.pin !== null) {
    context.addIssue({
      code: 'custom',
      path: ['hotdesk', 'pin'],
      message: 'A hotdesk PIN cannot be set and removed together.',
    })
  }

  if (input.hotdesk.require_pin && input.hotdesk.clear_pin) {
    context.addIssue({
      code: 'custom',
      path: ['hotdesk', 'clear_pin'],
      message: 'Disable PIN protection before removing the hotdesk PIN.',
    })
  }

  if (requireNewPin && input.hotdesk.require_pin && input.hotdesk.pin === null) {
    context.addIssue({
      code: 'custom',
      path: ['hotdesk', 'pin'],
      message: 'Enter a hotdesk PIN when PIN protection is enabled.',
    })
  }
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
        name: nullableString(128),
        device_type: starterDeviceTypeSchema.nullable(),
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
    validateCredentials(input, context, null)
    requireNewMailboxPin(input, context)
    validateHotdesk(input, context, true)

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

    if (
      input.device.mac_address !== null &&
      (!input.device.device_type || !provisionableStarterDeviceTypes.has(input.device.device_type))
    ) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'mac_address'],
        message: 'A MAC address is only available for provisionable desk, fax, and ATA devices.',
      })
    }

    if (!input.device.enabled && input.device.sip_username !== null) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'sip_username'],
        message: 'Enable the initial device before setting SIP credentials.',
      })
    }

    if (!input.device.enabled && input.device.sip_password !== null) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'sip_password'],
        message: 'Enable the initial device before setting SIP credentials.',
      })
    }
  })

export function extensionUpdateSchemaFor(currentUsername: string | null) {
  return z
    .object(userFields)
    .strict()
    .superRefine((input, context) => {
      validateCredentials(input, context, currentUsername)
      validateHotdesk(input, context, false)
    })
}

export const extensionUpdateSchema = z
  .object(userFields)
  .strict()
  .superRefine((input, context) => {
    validateCredentials(input, context)
    validateHotdesk(input, context, false)
  })
