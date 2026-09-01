import { z } from 'zod'
import { endpointAudioCodecs, endpointVideoCodecs } from '@/shared/switch/endpointMedia'
import { isSafeSwitchRegex } from '@/shared/forms/safeSwitchRegex'
import { nullableInteger } from '@/shared/forms/zod'
import { metaflowSettingsSchema } from '@/shared/switch/metaflows/schema'
import type { DeviceInput } from '@/domains/devices/types/device'
import { voicemailBoxFormSchemaFor } from '@/domains/voicemail/schemas/voicemailBoxFormSchema'

const nullableString = (maximum: number) => z.string().trim().max(maximum).nullable()
const uniqueValues = (values: string[]) => new Set(values).size === values.length
function voicemailAggregateSchema(editing: boolean, pinConfigured = false) {
  return z
    .object({
      enabled: z.boolean(),
      input: voicemailBoxFormSchemaFor(editing, pinConfigured).nullable(),
    })
    .strict()
    .superRefine((input, context) => {
      if (input.enabled && input.input === null) {
        context.addIssue({
          code: 'custom',
          path: ['input'],
          message: 'Configure the managed mailbox before saving the extension.',
        })
      }

      if (!input.enabled && input.input !== null) {
        context.addIssue({
          code: 'custom',
          path: ['input'],
          message: 'Enable voicemail before configuring its mailbox.',
        })
      }
    })
}

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
}

const recordingParametersSchema = z
  .object({
    enabled: z.boolean(),
    format: z.enum(['mp3', 'wav']),
    record_min_sec: nullableInteger(0, 3600),
    record_on_answer: z.boolean(),
    record_on_bridge: z.boolean(),
    record_sample_rate: z
      .union([z.literal(8000), z.literal(16000), z.literal(32000), z.literal(48000)])
      .nullable(),
    time_limit: nullableInteger(5, 10800),
  })
  .strict()
const recordingSourceSchema = z
  .object({
    any: recordingParametersSchema,
    onnet: recordingParametersSchema,
    offnet: recordingParametersSchema,
  })
  .strict()
const recordingRulesSchema = z
  .object({
    any: recordingSourceSchema,
    inbound: recordingSourceSchema,
    outbound: recordingSourceSchema,
  })
  .strict()

const advancedCallingFields = {
  caller_id: z
    .object({
      internal: z.object({ name: nullableString(35), number: nullableString(35) }).strict(),
      external: z
        .object({
          name: nullableString(35),
          phone_number_id: z.uuid().nullable(),
          preserve_number: z.boolean(),
        })
        .strict(),
      emergency: z
        .object({
          name: nullableString(35),
          phone_number_id: z.uuid().nullable(),
          preserve_number: z.boolean(),
        })
        .strict(),
    })
    .strict(),
  call_forward: z
    .object({
      enabled: z.boolean(),
      number: z
        .string()
        .trim()
        .max(35)
        .regex(/^[0-9+*#(),.\-\s]+$/, 'Enter an extension or dialable phone number.')
        .nullable(),
      direct_calls_only: z.boolean(),
      failover: z.boolean(),
      ignore_early_media: z.boolean(),
      keep_caller_id: z.boolean(),
      require_keypress: z.boolean(),
      substitute: z.boolean(),
    })
    .strict()
    .superRefine((input, context) => {
      if (input.enabled && input.number === null) {
        context.addIssue({
          code: 'custom',
          path: ['number'],
          message: 'Enter a forwarding destination.',
        })
      }
    }),
  call_restriction: z
    .record(
      z.string().regex(/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/),
      z.object({ action: z.enum(['inherit', 'deny']) }).strict(),
    )
    .refine((value) => Object.keys(value).length <= 100, 'Use no more than 100 restrictions.'),
  call_recording: recordingRulesSchema,
  media: z
    .object({
      audio: z
        .object({
          codecs: z
            .array(z.enum(endpointAudioCodecs))
            .max(endpointAudioCodecs.length)
            .refine(uniqueValues, 'Select each audio codec once.'),
        })
        .strict(),
      video: z
        .object({
          codecs: z
            .array(z.enum(endpointVideoCodecs))
            .max(endpointVideoCodecs.length)
            .refine(uniqueValues, 'Select each video codec once.'),
        })
        .strict(),
      bypass_media: z.union([z.boolean(), z.literal('auto')]),
      encryption: z
        .object({
          enforce_security: z.boolean(),
          methods: z
            .array(z.enum(['srtp', 'zrtp']))
            .max(2)
            .refine(uniqueValues, 'Select each encryption method once.'),
        })
        .strict(),
      fax_option: z.boolean(),
      ignore_early_media: z.boolean(),
      progress_timeout: nullableInteger(0, 3600),
    })
    .strict(),
  music_on_hold: z
    .object({
      media_id: z.uuid().nullable(),
      preserve_media: z.boolean(),
    })
    .strict(),
  ringtones: z
    .object({
      internal: nullableString(256),
      external: nullableString(256),
    })
    .strict(),
  dial_plan: z
    .object({
      system: z.array(z.string().trim().min(1).max(255)).max(64),
      rules: z
        .array(
          z
            .object({
              pattern: z
                .string()
                .trim()
                .min(1, 'Enter a dial-plan pattern.')
                .max(512)
                .refine(isSafeSwitchRegex, 'Enter a supported regular expression.'),
              description: nullableString(255),
              prefix: nullableString(64),
              suffix: nullableString(64),
            })
            .strict(),
        )
        .max(64),
    })
    .strict()
    .superRefine((value, context) => {
      const patterns = new Set<string>()

      value.rules.forEach((rule, index) => {
        if (patterns.has(rule.pattern)) {
          context.addIssue({
            code: 'custom',
            path: ['rules', index, 'pattern'],
            message: 'Dial-plan patterns must be unique.',
          })
        }

        patterns.add(rule.pattern)
      })
    }),
  formatters: z
    .array(
      z
        .object({
          field: z
            .string()
            .trim()
            .min(1, 'Enter the Switch field to format.')
            .max(128)
            .regex(/^[A-Za-z0-9_]+$/, 'Use only letters, numbers, and underscores.'),
          direction: z.enum(['inbound', 'outbound', 'both']).nullable(),
          match_invite_format: z.boolean(),
          prefix: nullableString(1024),
          regex: nullableString(2048).refine(
            (value) => value === null || isSafeSwitchRegex(value),
            'Enter a supported regular expression.',
          ),
          strip: z.boolean(),
          suffix: nullableString(1024),
          value: nullableString(1024),
        })
        .strict(),
    )
    .max(64),
  profile: z
    .object({
      addresses: z
        .array(
          z
            .object({
              address: z.string().trim().min(1, 'Enter an address.').max(512),
              types: z
                .array(z.enum(['dom', 'postal', 'intl', 'parcel', 'home', 'work', 'pref']))
                .max(7)
                .refine(uniqueValues, 'Select each address type once.'),
            })
            .strict(),
        )
        .max(20),
      assistant: nullableString(255),
      birthday: nullableString(64),
      nicknames: z
        .array(z.string().trim().min(1).max(255))
        .max(20)
        .refine(uniqueValues, 'Use each nickname once.'),
      note: nullableString(2000),
      role: nullableString(255),
      sort_string: nullableString(255),
      title: nullableString(255),
    })
    .strict(),
  pronounced_name: z
    .object({
      media_id: z.uuid().nullable(),
      preserve_media: z.boolean(),
    })
    .strict(),
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

export const extensionCreateSchema = z
  .object({
    ...userFields,
    caller_id: advancedCallingFields.caller_id,
    call_forward: advancedCallingFields.call_forward,
    call_restriction: advancedCallingFields.call_restriction,
    call_recording: advancedCallingFields.call_recording,
    media: advancedCallingFields.media.default({
      audio: { codecs: [] },
      video: { codecs: [] },
      bypass_media: false,
      encryption: { enforce_security: false, methods: [] },
      fax_option: false,
      ignore_early_media: false,
      progress_timeout: null,
    }),
    music_on_hold: advancedCallingFields.music_on_hold.default({
      media_id: null,
      preserve_media: false,
    }),
    ringtones: advancedCallingFields.ringtones.default({ internal: null, external: null }),
    dial_plan: advancedCallingFields.dial_plan.default({ system: [], rules: [] }),
    formatters: advancedCallingFields.formatters.default([]),
    profile: advancedCallingFields.profile.default({
      addresses: [],
      assistant: null,
      birthday: null,
      nicknames: [],
      note: null,
      role: null,
      sort_string: null,
      title: null,
    }),
    pronounced_name: advancedCallingFields.pronounced_name.default({
      media_id: null,
      preserve_media: false,
    }),
    metaflows: metaflowSettingsSchema.default({
      binding_digit: null,
      digit_timeout: null,
      listen_on: null,
      actions: [],
    }),
    voicemail: voicemailAggregateSchema(false),
    device: z
      .object({
        enabled: z.boolean(),
        input: z.custom<DeviceInput>((value) => value === null || typeof value === 'object'),
      })
      .strict(),
  })
  .strict()
  .superRefine((input, context) => {
    validateCredentials(input, context, null)
    validateHotdesk(input, context, true)

    for (const scope of ['external', 'emergency'] as const) {
      if (input.caller_id[scope].preserve_number) {
        context.addIssue({
          code: 'custom',
          path: ['caller_id', scope, 'preserve_number'],
          message: 'A new Switch user has no existing caller-ID number to preserve.',
        })
      }
    }

    if (input.device.enabled && input.device.input === null) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'input'],
        message: 'Configure the initial device before creating the extension.',
      })
    }

    if (!input.device.enabled && input.device.input !== null) {
      context.addIssue({
        code: 'custom',
        path: ['device', 'input'],
        message: 'Enable the initial device before configuring it.',
      })
    }
  })

export function extensionUpdateSchemaFor(
  currentUsername: string | null,
  voicemailPinConfigured = false,
) {
  return z
    .object({
      ...userFields,
      voicemail: voicemailAggregateSchema(true, voicemailPinConfigured),
      ...advancedCallingFields,
      metaflows: metaflowSettingsSchema.optional(),
    })
    .strict()
    .superRefine((input, context) => {
      validateCredentials(input, context, currentUsername)
      validateHotdesk(input, context, false)
    })
}

export const extensionUpdateSchema = z
  .object({
    ...userFields,
    voicemail: voicemailAggregateSchema(true, true),
    ...advancedCallingFields,
    metaflows: metaflowSettingsSchema.optional(),
  })
  .strict()
  .superRefine((input, context) => {
    validateCredentials(input, context)
    validateHotdesk(input, context, false)
  })
