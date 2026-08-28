import { z } from 'zod'
import { audioCodecs, videoCodecs } from '../deviceForm'

const nullableString = (maximum: number) => z.string().trim().max(maximum).nullable()
const nullableInteger = (minimum: number, maximum: number) =>
  z.number().int().min(minimum).max(maximum).nullable()

const callerIdIdentitySchema = z
  .object({
    name: nullableString(35),
    number: nullableString(35),
  })
  .strict()

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

const sipHeaderSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, 'Enter a header name.')
      .max(128)
      .regex(/^[A-Za-z0-9_-]+$/, 'Use only letters, numbers, underscores, and hyphens.')
      .refine(
        (name) => !/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i.test(name),
        'Sensitive authentication headers cannot be configured here.',
      ),
    value: z.string().trim().min(1, 'Enter a header value.').max(1024),
  })
  .strict()

const sipSchema = z
  .object({
    method: z.enum(['password', 'ip']),
    username: z.string().trim().min(2).max(32).nullable(),
    password: z.string().min(12, 'Use at least 12 characters.').max(32).nullable(),
    realm: z
      .string()
      .trim()
      .min(4)
      .max(253)
      .regex(/^[.\w_-]+$/, 'Use only letters, numbers, periods, underscores, and hyphens.')
      .nullable(),
    expire_seconds: nullableInteger(30, 86400),
    invite_format: z.enum(['username', 'npan', '1npan', 'e164', 'route', 'contact']),
    ip: z.union([z.ipv4(), z.ipv6()]).nullable(),
    number: nullableString(64),
    route: nullableString(2048),
    static_route: nullableString(2048),
    ignore_completed_elsewhere: z.boolean(),
    custom_sip_headers: z
      .object({ in: z.array(sipHeaderSchema).max(50), out: z.array(sipHeaderSchema).max(50) })
      .strict(),
  })
  .strict()
  .superRefine((sip, context) => {
    if (sip.method === 'ip' && sip.ip === null) {
      context.addIssue({
        code: 'custom',
        path: ['ip'],
        message: 'Enter the IP address authorized to use this device.',
      })
    }
  })

export const deviceFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a device name.').max(255),
    device_type: z.enum([
      'sip_device',
      'cellphone',
      'smartphone',
      'softphone',
      'landline',
      'fax',
      'ata',
      'sip_uri',
    ]),
    provision: z
      .object({
        endpoint_brand: nullableString(255),
        endpoint_family: nullableString(255),
        endpoint_model: nullableString(255),
      })
      .strict()
      .optional(),
    mac_address: z
      .string()
      .trim()
      .max(64)
      .regex(/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/, 'Enter a valid MAC address.')
      .nullable(),
    is_enabled: z.boolean(),
    assigned_extension_id: z.uuid().nullable(),
    call_forward: z
      .object({
        enabled: z.boolean(),
        number: nullableString(15),
        direct_calls_only: z.boolean(),
        failover: z.boolean(),
        ignore_early_media: z.boolean(),
        keep_caller_id: z.boolean(),
        require_keypress: z.boolean(),
        substitute: z.boolean(),
      })
      .strict()
      .optional(),
    sip: sipSchema.optional(),
    media: z
      .object({
        audio: z.object({ codecs: z.array(z.enum(audioCodecs)).max(audioCodecs.length) }).strict(),
        video: z.object({ codecs: z.array(z.enum(videoCodecs)).max(videoCodecs.length) }).strict(),
        bypass_media: z.union([z.boolean(), z.literal('auto')]),
        encryption: z
          .object({
            enforce_security: z.boolean(),
            methods: z.array(z.enum(['zrtp', 'srtp'])).max(2),
          })
          .strict(),
        fax_option: z.boolean(),
        ignore_early_media: z.boolean(),
        progress_timeout: nullableInteger(0, 3600),
      })
      .strict()
      .optional(),
    caller_id: z
      .object({
        internal: callerIdIdentitySchema,
        external: callerIdIdentitySchema,
        emergency: callerIdIdentitySchema,
        asserted: callerIdIdentitySchema.extend({ realm: nullableString(253) }).strict(),
      })
      .strict()
      .optional(),
    caller_id_options: z
      .object({ outbound_privacy: z.enum(['full', 'name', 'number', 'none']) })
      .strict()
      .optional(),
    call_waiting: z.object({ enabled: z.boolean() }).strict().optional(),
    do_not_disturb: z.object({ enabled: z.boolean() }).strict().optional(),
    contact_list: z.object({ exclude: z.boolean() }).strict().optional(),
    exclude_from_queues: z.boolean().optional(),
    language: nullableString(32).optional(),
    timezone: nullableString(255).optional(),
    presence_id: nullableString(255).optional(),
    mwi_unsolicited_updates: z.boolean().optional(),
    register_overwrite_notify: z.boolean().optional(),
    suppress_unregister_notifications: z.boolean().optional(),
    ringtones: z
      .object({ internal: nullableString(256), external: nullableString(256) })
      .strict()
      .optional(),
    call_restriction: z
      .record(z.string(), z.object({ action: z.enum(['inherit', 'deny']) }).strict())
      .optional(),
    call_recording: z
      .object({
        any: recordingSourceSchema,
        inbound: recordingSourceSchema,
        outbound: recordingSourceSchema,
      })
      .strict()
      .optional(),
    music_on_hold: z.object({ media_id: z.uuid().nullable() }).strict().optional(),
    outbound_flags: z
      .object({
        static: z.array(z.string().trim().min(1).max(255)).max(64),
        dynamic: z.array(z.string().trim().min(1).max(255)).max(64),
      })
      .strict()
      .optional(),
    dial_plan: z
      .object({
        system: z.array(z.string().trim().min(1).max(255)).max(64),
        rules: z
          .array(
            z
              .object({
                pattern: z.string().trim().min(1, 'Enter a dial-plan pattern.').max(512),
                description: nullableString(255),
                prefix: nullableString(64),
                suffix: nullableString(64),
              })
              .strict(),
          )
          .max(64),
      })
      .strict()
      .optional(),
    metaflows: z
      .object({
        binding_digit: z
          .enum(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#'])
          .nullable(),
        digit_timeout: nullableInteger(0, 60000),
        listen_on: z.enum(['both', 'self', 'peer']).nullable(),
      })
      .strict()
      .optional(),
  })
  .strict()
  .superRefine((device, context) => {
    for (const direction of ['in', 'out'] as const) {
      const names = new Set<string>()

      device.sip?.custom_sip_headers[direction].forEach((header, index) => {
        const normalized = header.name.toLowerCase()

        if (names.has(normalized)) {
          context.addIssue({
            code: 'custom',
            path: ['sip', 'custom_sip_headers', direction, index, 'name'],
            message: 'Header names must be unique in this direction.',
          })
        }

        names.add(normalized)
      })
    }

    const dialPlanPatterns = new Set<string>()
    device.dial_plan?.rules.forEach((rule, index) => {
      if (dialPlanPatterns.has(rule.pattern)) {
        context.addIssue({
          code: 'custom',
          path: ['dial_plan', 'rules', index, 'pattern'],
          message: 'Dial-plan patterns must be unique.',
        })
      }

      dialPlanPatterns.add(rule.pattern)
    })

    if (
      ['cellphone', 'smartphone', 'landline'].includes(device.device_type) &&
      device.call_forward?.enabled &&
      device.call_forward.number === null
    ) {
      context.addIssue({
        code: 'custom',
        path: ['call_forward', 'number'],
        message: 'Enter the number that should receive forwarded calls.',
      })
    }

    if (device.device_type === 'sip_uri' && device.sip?.route === null) {
      context.addIssue({
        code: 'custom',
        path: ['sip', 'route'],
        message: 'Enter the SIP URI that should receive calls.',
      })
    }
  })

export type ValidatedDeviceInput = z.infer<typeof deviceFormSchema>
