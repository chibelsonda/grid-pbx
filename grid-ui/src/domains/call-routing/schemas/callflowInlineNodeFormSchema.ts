import { z } from 'zod'
import {
  callflowTreeBranchKeys,
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
  z.preprocess((value) => (value === '' ? null : value), z.string().trim().max(maximum).nullable())

const nullableInteger = (minimum: number, maximum: number) =>
  z.preprocess(
    (value) => (value === '' ? null : value),
    z.number().int().min(minimum).max(maximum).nullable(),
  )

const terminators = z
  .array(z.enum(callflowDtmfDigits))
  .max(callflowDtmfDigits.length)
  .refine((values) => new Set(values).size === values.length, 'Choose each terminator once.')

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
} satisfies Record<CallflowInlineModule, z.ZodType>

export function createCallflowInlineNodeFormSchema(
  module: CallflowInlineModule,
  branchKeys: CallflowTreeBranchKey[],
  branchRequired: boolean,
) {
  const branches = new Set(branchKeys)

  return z
    .object({
      branch: z.enum(callflowTreeBranchKeys).nullable(),
      data: schemas[module],
    })
    .strict()
    .superRefine((input, context) => {
      if (branchRequired && (input.branch === null || !branches.has(input.branch))) {
        context.addIssue({
          code: 'custom',
          path: ['branch'],
          message: 'Select an available empty branch.',
        })
      }
    })
}
