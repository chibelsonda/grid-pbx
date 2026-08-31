import { z } from 'zod'
import { isSafeSwitchRegex } from '@/shared/forms/safeSwitchRegex'
import { metaflowSettingsSchema } from '@/shared/switch/metaflows/schema'

const optionalText = (maximum: number, minimum = 0) =>
  z
    .string()
    .trim()
    .max(maximum)
    .refine(
      (value) => value === '' || value.length >= minimum,
      `Enter at least ${minimum} characters.`,
    )
    .transform((value) => value || null)
const optionalRegex = (maximum: number) =>
  z
    .string()
    .trim()
    .max(maximum)
    .refine(
      (value) => value === '' || isSafeSwitchRegex(value),
      'Enter a supported regular expression.',
    )
    .transform((value) => value || null)

const nullableInteger = (minimum: number, maximum: number) =>
  z.number().int().min(minimum).max(maximum).nullable()

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

export const accountSettingsSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter an account name.').max(128),
    organization_name: optionalText(255),
    timezone: optionalText(32, 5),
    language: optionalText(32),
    call_waiting_enabled: z.boolean(),
    do_not_disturb_enabled: z.boolean(),
    outbound_privacy: z.enum(['full', 'name', 'number', 'none']).nullable(),
    show_rate: z.boolean(),
    ringtone_internal: optionalText(256),
    ringtone_external: optionalText(256),
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
    call_restriction: z
      .record(
        z.string().regex(/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/),
        z.object({ action: z.enum(['inherit', 'deny']) }).strict(),
      )
      .refine((value) => Object.keys(value).length <= 100, 'Use no more than 100 restrictions.'),
    call_recording: z
      .object({ account: recordingRulesSchema, endpoint: recordingRulesSchema })
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
                description: optionalText(255),
                prefix: optionalText(64),
                suffix: optionalText(64),
              })
              .strict(),
          )
          .max(64),
      })
      .strict(),
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
            prefix: optionalText(1024),
            regex: optionalRegex(2048),
            strip: z.boolean(),
            suffix: optionalText(1024),
            value: optionalText(1024),
          })
          .strict(),
      )
      .max(64),
    preflow: z
      .object({
        callflow_id: z.string().uuid().nullable(),
        preserve_callflow: z.boolean(),
      })
      .strict(),
    metaflows: metaflowSettingsSchema,
  })
  .superRefine((settings, context) => {
    const patterns = new Set<string>()

    settings.dial_plan.rules.forEach((rule, index) => {
      if (patterns.has(rule.pattern)) {
        context.addIssue({
          code: 'custom',
          path: ['dial_plan', 'rules', index, 'pattern'],
          message: 'Dial-plan patterns must be unique.',
        })
      }

      patterns.add(rule.pattern)
    })
  })
