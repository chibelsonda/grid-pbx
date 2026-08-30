import { z } from 'zod'
import { isSafeSwitchRegex } from '@/shared/forms/safeSwitchRegex'
import {
  isCallflowTreeBranchKey,
  isCallflowCapturedNumberBranchKey,
  type CallflowInlineModule,
  type CallflowTreeBranchKey,
} from '../types/callRouting'

export const callflowDtmfDigits = [
  '1',
  '2',
  '3',
  '4',
  '5',
  '6',
  '7',
  '8',
  '9',
  '0',
  '#',
  '*',
] as const

const nullableString = (maximum: number) =>
  z.preprocess(
    (value) => (typeof value === 'string' && value.trim() === '' ? null : value),
    z.string().trim().max(maximum).nullable(),
  )

const nullableInteger = (minimum: number, maximum: number) =>
  z.preprocess(
    (value) => (value === '' ? null : value),
    z.number().int().min(minimum).max(maximum).nullable(),
  )

const terminators = z
  .array(z.enum(callflowDtmfDigits))
  .max(callflowDtmfDigits.length)
  .refine((values) => new Set(values).size === values.length, 'Choose each terminator once.')

const customApplicationVariables = z
  .array(
    z.object({
      key: z
        .string()
        .trim()
        .min(1, 'Enter a variable name.')
        .max(128)
        .regex(/^[A-Za-z0-9_-]+$/, 'Use only letters, numbers, hyphens, and underscores.'),
      value: z
        .string()
        .max(1024)
        .refine(
          (value) => !/[\x00\r\n]/.test(value),
          'Values cannot contain line breaks or null bytes.',
        ),
    }),
  )
  .max(64, 'Add no more than 64 custom application variables.')
  .superRefine((variables, context) => {
    const positions = new Map<string, number>()

    variables.forEach(({ key }, index) => {
      const normalized = key.trim()
      const previous = positions.get(normalized)

      if (previous === undefined) {
        positions.set(normalized, index)
        return
      }

      const message = 'Variable names must be unique.'
      context.addIssue({ code: 'custom', path: [previous, 'key'], message })
      context.addIssue({ code: 'custom', path: [index, 'key'], message })
    })
  })

const ringGroupEndpoints = z
  .array(
    z
      .object({
        device_id: z.string().uuid('Select a synchronized device.'),
        delay: z.number().int().min(0).max(60),
        timeout: z.number().int().min(1).max(60),
        weight: z.number().int().min(1).max(100).optional(),
      })
      .strict(),
  )
  .min(1, 'Select at least one device.')
  .max(20, 'Select no more than 20 devices.')
  .refine(
    (endpoints) => new Set(endpoints.map(({ device_id }) => device_id)).size === endpoints.length,
    'Choose each device once.',
  )

const schemas = {
  sleep: z
    .object({
      duration: z.number().int().min(0).max(86_400_000),
      unit: z.enum(['ms', 's', 'm', 'h']),
      skip_module: z.boolean(),
    })
    .strict(),
  tts: z
    .object({
      text: z.string().trim().min(1, 'Enter the text that Switch should speak.').max(1000),
      voice: nullableString(64),
      language: nullableString(35),
      engine: z.enum(['flite', 'google', 'ispeech', 'voicefabric']).nullable(),
      endless_playback: z.boolean(),
      terminators,
      skip_module: z.boolean(),
    })
    .strict(),
  collect_dtmf: z
    .object({
      collection_name: nullableString(128),
      interdigit_timeout: z.number().int().min(1).max(86_400_000),
      max_digits: z.number().int().min(1).max(128),
      terminators,
      timeout: z.number().int().min(1).max(86_400_000),
      skip_module: z.boolean(),
    })
    .strict(),
  record_call: z
    .object({
      action: z.enum(['start', 'stop']),
      format: z.enum(['mp3', 'wav']).nullable(),
      label: nullableString(128),
      record_min_sec: nullableInteger(0, 10_800),
      record_on_answer: z.boolean(),
      record_on_bridge: z.boolean(),
      record_sample_rate: nullableInteger(8000, 192_000),
      should_follow_transfer: z.boolean(),
      time_limit: z.number().int().min(5).max(10_800),
      skip_module: z.boolean(),
    })
    .strict(),
  record_caller: z
    .object({
      format: z.enum(['mp3', 'wav']).nullable(),
      time_limit: z.number().int().min(5).max(10_800),
      skip_module: z.boolean(),
    })
    .strict(),
  send_dtmf: z
    .object({
      digits: z.string().trim().min(1, 'Enter the DTMF digits to send.').max(128),
      duration_ms: z.number().int().min(1).max(60_000),
      skip_module: z.boolean(),
    })
    .strict(),
  flush_dtmf: z
    .object({
      collection_name: z.string().trim().min(1).max(128),
      skip_module: z.boolean(),
    })
    .strict(),
  dead_air: z.object({ skip_module: z.boolean() }).strict(),
  language: z
    .object({
      language: z
        .string()
        .trim()
        .regex(/^[A-Za-z]{2}(?:-[A-Za-z]{2})?$/, 'Use a language code such as en or en-US.'),
      skip_module: z.boolean(),
    })
    .strict(),
  response: z
    .object({
      code: z.number().int().min(400).max(699),
      message: nullableString(128),
      skip_module: z.boolean(),
    })
    .strict(),
  hangup: z.object({ skip_module: z.boolean() }).strict(),
  set_variable: z
    .object({
      variable: z.literal('call_priority'),
      value: z
        .string()
        .trim()
        .regex(/^\d{1,3}$/, 'Enter a whole-number priority from 0 through 255.')
        .refine((value) => Number(value) <= 255, 'Enter a priority from 0 through 255.'),
      channel: z.enum(['a', 'both']),
      skip_module: z.boolean(),
    })
    .strict(),
  set_variables: z
    .object({
      custom_application_variables: customApplicationVariables,
      export: z.boolean(),
      skip_module: z.boolean(),
    })
    .strict(),
  manual_presence: z
    .object({
      presence_id: z
        .string()
        .trim()
        .min(1, 'Enter a presence ID.')
        .max(256)
        .regex(
          /^[^\s@]+(?:@[^\s@]+)?$/u,
          'Enter a presence ID such as 1001 or 1001@example.com without spaces.',
        ),
      status: z.enum(['idle', 'ringing', 'busy']),
      skip_module: z.boolean(),
    })
    .strict(),
  group_pickup: z
    .object({
      target_type: z.enum(['extension', 'device', 'group']),
      target_id: z.string().uuid('Select a synchronized pickup target.'),
      skip_module: z.boolean(),
    })
    .strict(),
  page_group: z
    .object({
      audio: z.enum(['one-way', 'two-way']),
      device_ids: z
        .array(z.string().uuid('Select a synchronized device.'))
        .min(1, 'Select at least one device.')
        .max(20, 'Select no more than 20 devices.')
        .refine((values) => new Set(values).size === values.length, 'Choose each device once.'),
      skip_module: z.boolean(),
    })
    .strict(),
  ring_group: z
    .object({
      strategy: z.enum(['simultaneous', 'single', 'weighted_random']),
      endpoints: ringGroupEndpoints,
      repeats: z.number().int().min(1).max(3),
      ignore_forward: z.boolean(),
      fail_on_single_reject: z.boolean(),
      skip_module: z.boolean(),
    })
    .strict()
    .superRefine(({ strategy, endpoints }, context) => {
      if (strategy === 'single' || strategy === 'weighted_random') {
        endpoints.forEach(({ delay }, index) => {
          if (delay !== 0) {
            context.addIssue({
              code: 'custom',
              path: ['endpoints', index, 'delay'],
              message: 'Sequential Ring Group strategies cannot use a delay.',
            })
          }
        })
      }

      endpoints.forEach(({ weight }, index) => {
        if (strategy === 'weighted_random' && weight === undefined) {
          context.addIssue({
            code: 'custom',
            path: ['endpoints', index, 'weight'],
            message: 'Enter a weight from 1 through 100 for weighted-random routing.',
          })
        } else if (strategy !== 'weighted_random' && weight !== undefined) {
          context.addIssue({
            code: 'custom',
            path: ['endpoints', index, 'weight'],
            message: 'Weights are available only for weighted-random routing.',
          })
        }
      })

      const attemptTimeout =
        strategy === 'single' || strategy === 'weighted_random'
          ? endpoints.reduce((total, endpoint) => total + endpoint.timeout, 0)
          : Math.max(...endpoints.map((endpoint) => endpoint.delay + endpoint.timeout), 0)

      if (attemptTimeout > 120) {
        context.addIssue({
          code: 'custom',
          path: ['endpoints'],
          message: 'Keep the total Ring Group attempt duration within 120 seconds.',
        })
      }
    }),
  receive_fax: z
    .object({
      owner_id: z.string().uuid('Select a synchronized extension.'),
      fax_option: z.union([z.literal('auto'), z.boolean()]),
      skip_module: z.boolean(),
    })
    .strict(),
  conference: z
    .object({
      service_mode: z.literal(true),
      skip_module: z.boolean(),
    })
    .strict(),
  voicemail: z
    .object({
      action: z.literal('check'),
      skip_module: z.boolean(),
    })
    .strict(),
  branch_variable: z
    .object({
      variable: z.literal('call_priority'),
      scope: z.literal('custom_channel_vars'),
      skip_module: z.boolean(),
    })
    .strict(),
  branch_bnumber: z
    .object({
      hunt: z.boolean(),
      hunt_allow: nullableString(512).refine(
        (value) => value === null || isSafeSwitchRegex(value),
        'Enter a supported regular expression.',
      ),
      hunt_deny: nullableString(512).refine(
        (value) => value === null || isSafeSwitchRegex(value),
        'Enter a supported regular expression.',
      ),
      skip_module: z.boolean(),
    })
    .strict()
    .superRefine((data, context) => {
      if (data.hunt || (data.hunt_allow === null && data.hunt_deny === null)) return

      const message = 'Hunt filters require hunt mode.'
      if (data.hunt_allow !== null) {
        context.addIssue({ code: 'custom', path: ['hunt_allow'], message })
      }
      if (data.hunt_deny !== null) {
        context.addIssue({ code: 'custom', path: ['hunt_deny'], message })
      }
    }),
  missed_call_alert: z
    .object({
      recipients: z
        .array(
          z.discriminatedUnion('type', [
            z.object({
              type: z.literal('user'),
              id: z.string().uuid('Select a synchronized extension.'),
            }),
            z.object({
              type: z.literal('email'),
              id: z.string().trim().email('Enter a valid email address.').max(254),
            }),
          ]),
        )
        .min(1, 'Add at least one notification recipient.')
        .max(50),
      skip_module: z.boolean(),
    })
    .strict(),
  set_cid: z
    .object({
      caller_id_name: z.string().trim().max(128),
      caller_id_number: z.string().trim().max(64),
      skip_module: z.boolean(),
    })
    .strict(),
  prepend_cid: z
    .object({
      action: z.enum(['reset', 'prepend']),
      apply_to: z.enum(['original', 'current']),
      caller_id_name_prefix: z.string().max(128),
      caller_id_number_prefix: z.string().max(64),
      skip_module: z.boolean(),
    })
    .strict(),
  set_alert_info: z
    .object({
      alert_info: z
        .string()
        .trim()
        .min(1, 'Enter an Alert-Info value.')
        .max(256)
        .refine((value) => !/[\r\n]/.test(value), 'Alert-Info cannot contain line breaks.'),
      skip_module: z.boolean(),
    })
    .strict(),
  check_cid: z
    .object({
      regex: z
        .string()
        .trim()
        .min(1, 'Enter a caller ID pattern.')
        .max(512)
        .refine(isSafeSwitchRegex, 'Enter a supported regular expression.'),
      use_absolute_mode: z.literal(false),
      external_caller_id_name: nullableString(128),
      external_caller_id_number: nullableString(64),
      user_id: z.string().uuid('Select a synchronized extension.').nullable(),
      skip_module: z.boolean(),
    })
    .strict()
    .superRefine((data, context) => {
      const identity = [data.external_caller_id_name, data.external_caller_id_number, data.user_id]
      const configured = identity.filter((value) => value !== null)

      if (configured.length === 0 || configured.length === identity.length) return

      const message = 'Complete all caller identity override fields or clear all three.'
      if (data.external_caller_id_name === null) {
        context.addIssue({ code: 'custom', path: ['external_caller_id_name'], message })
      }
      if (data.external_caller_id_number === null) {
        context.addIssue({ code: 'custom', path: ['external_caller_id_number'], message })
      }
      if (data.user_id === null) {
        context.addIssue({ code: 'custom', path: ['user_id'], message })
      }
    }),
  cidlistmatch: z
    .object({
      caller_id_list_id: z.string().uuid('Select a synchronized Caller-ID List.'),
      skip_module: z.boolean(),
    })
    .strict(),
  temporal_route: z
    .object({
      action: z.enum(['disable', 'enable', 'reset']),
      rules: z.array(z.string().uuid('Select a synchronized temporal rule.')).max(250),
      skip_module: z.boolean(),
    })
    .strict(),
  ring_group_toggle: z
    .object({
      action: z.enum(['login', 'logout']),
      callflow_id: z.string().uuid('Select a synchronized ring-group callflow.'),
      skip_module: z.boolean(),
    })
    .strict(),
  acdc_queue: z
    .object({
      action: z.enum(['login', 'logout']),
      queue_id: z.string().uuid('Select a synchronized queue.'),
      skip_module: z.boolean(),
    })
    .strict(),
  hotdesk: z
    .object({
      action: z.enum(['login', 'logout', 'toggle']),
      skip_module: z.boolean(),
    })
    .strict(),
  do_not_disturb: z
    .object({
      action: z.enum(['activate', 'deactivate', 'toggle']),
      skip_module: z.boolean(),
    })
    .strict(),
} satisfies Record<CallflowInlineModule, z.ZodType>

export function createCallflowInlineNodeFormSchema(
  module: CallflowInlineModule,
  branchKeys: CallflowTreeBranchKey[],
  branchRequired: boolean,
  allowCapturedNumberBranch = false,
  occupiedBranchKeys: string[] = [],
) {
  const branches = new Set(branchKeys)
  const occupied = new Set(occupiedBranchKeys)

  return z
    .object({
      branch: z
        .custom<CallflowTreeBranchKey>(
          isCallflowTreeBranchKey,
          'Choose a supported callflow branch.',
        )
        .nullable(),
      data: schemas[module],
    })
    .strict()
    .superRefine((input, context) => {
      const customCapturedNumber =
        allowCapturedNumberBranch &&
        isCallflowCapturedNumberBranchKey(input.branch) &&
        !occupied.has(input.branch)

      if (
        branchRequired &&
        (input.branch === null || (!branches.has(input.branch) && !customCapturedNumber))
      ) {
        context.addIssue({
          code: 'custom',
          path: ['branch'],
          message: 'Select an available empty branch.',
        })
      }
    })
}
