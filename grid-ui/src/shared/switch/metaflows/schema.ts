import { z } from 'zod'
import { isSafeSwitchRegex } from '@/shared/forms/safeSwitchRegex'
import type { MetaflowChild, MetaflowNode } from './types'

export const metaflowModules = [
  'audio_level',
  'break',
  'callflow',
  'hangup',
  'hold_control',
  'move',
  'play',
  'record_call',
  'resume',
  'say',
  'sound_touch',
  'transfer',
  'tts',
] as const

const metaflowFields: Record<(typeof metaflowModules)[number], string[]> = {
  audio_level: ['action', 'level', 'mode'],
  break: [],
  callflow: ['callflow_id', 'captures', 'collected'],
  hangup: [],
  hold_control: ['action'],
  move: ['device_id', 'extension_id', 'auto_answer', 'can_call_self', 'dial_strategy'],
  play: ['media_id', 'leg'],
  record_call: [
    'action',
    'dtmf_leg',
    'format',
    'label',
    'media_name',
    'origin',
    'record_min_sec',
    'record_on_answer',
    'record_on_bridge',
    'record_sample_rate',
    'time_limit',
  ],
  resume: [],
  say: ['gender', 'language', 'method', 'text', 'type'],
  sound_touch: [
    'action',
    'adjust_in_octaves',
    'adjust_in_semitones',
    'hook_dtmf',
    'pitch',
    'rate',
    'sending_leg',
    'tempo',
  ],
  transfer: ['leg', 'target', 'transfer_type'],
  tts: ['engine', 'language', 'leg', 'text', 'voice'],
}

function validateNode(node: Pick<MetaflowNode, 'module' | 'data'>, context: z.RefinementCtx): void {
  Object.keys(node.data).forEach((field) => {
    if (!metaflowFields[node.module].includes(field)) {
      context.addIssue({
        code: 'custom',
        path: ['data'],
        message: 'The selected metaflow action contains an unsupported field.',
      })
    }
  })

  if (node.module === 'play' && !node.data.media_id) {
    context.addIssue({
      code: 'custom',
      path: ['data', 'media_id'],
      message: 'Select media to play.',
    })
  }

  if (node.module === 'callflow' && !node.data.callflow_id) {
    context.addIssue({
      code: 'custom',
      path: ['data', 'callflow_id'],
      message: 'Select a callflow to run.',
    })
  }

  if (node.module === 'move' && !node.data.device_id && !node.data.extension_id) {
    context.addIssue({
      code: 'custom',
      path: ['data'],
      message: 'Select a destination device or extension.',
    })
  }

  const resourceFields =
    node.module === 'play'
      ? ['media_id']
      : node.module === 'callflow'
        ? ['callflow_id']
        : node.module === 'move'
          ? ['device_id', 'extension_id']
          : []

  resourceFields.forEach((field) => {
    const value = node.data[field]

    if (value !== undefined && !z.uuid().safeParse(value).success) {
      context.addIssue({
        code: 'custom',
        path: ['data', field],
        message: 'Select a projected resource.',
      })
    }
  })
}

const dataSchema = z.record(
  z.string(),
  z.union([z.string().max(2048), z.number(), z.boolean(), z.null()]),
)

const childSchema: z.ZodType<MetaflowChild> = z.lazy(() =>
  z
    .object({
      key: z.string().trim().min(1, 'Enter a branch key.').max(64),
      module: z.enum(metaflowModules),
      data: dataSchema,
      children: z.array(childSchema).max(20).default([]),
    })
    .strict()
    .superRefine(validateNode),
)

const actionSchema = z
  .object({
    trigger_type: z.enum(['number', 'pattern']),
    trigger: z.string().trim().min(1, 'Enter a metaflow trigger.').max(255),
    module: z.enum(metaflowModules),
    data: dataSchema,
    children: z.array(childSchema).max(20).default([]),
  })
  .strict()
  .superRefine(validateNode)

export const metaflowSettingsSchema = z
  .object({
    binding_digit: z.enum(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#']).nullable(),
    digit_timeout: z.number().int().min(0).max(60000).nullable(),
    listen_on: z.enum(['both', 'self', 'peer']).nullable(),
    actions: z.array(actionSchema).max(50).default([]),
  })
  .strict()
  .superRefine((metaflows, context) => {
    const triggers = new Set<string>()
    let nodeCount = 0

    const inspectChildren = (
      children: MetaflowChild[],
      path: Array<string | number>,
      depth: number,
    ): void => {
      const keys = new Set<string>()

      children.forEach((child, index) => {
        nodeCount += 1

        if (depth > 8) {
          context.addIssue({
            code: 'custom',
            path: [...path, index, 'children'],
            message: 'Metaflow branches may be at most 8 levels deep.',
          })
        }

        if (keys.has(child.key)) {
          context.addIssue({
            code: 'custom',
            path: [...path, index, 'key'],
            message: 'Branch keys must be unique at this level.',
          })
        }
        keys.add(child.key)
        inspectChildren(child.children, [...path, index, 'children'], depth + 1)
      })
    }

    metaflows.actions.forEach((action, index) => {
      nodeCount += 1
      const identity = `${action.trigger_type}:${action.trigger}`

      if (action.trigger_type === 'number' && !/^[0-9]+$/.test(action.trigger)) {
        context.addIssue({
          code: 'custom',
          path: ['actions', index, 'trigger'],
          message: 'Number metaflow triggers may contain digits only.',
        })
      }

      if (action.trigger_type === 'pattern' && !isSafeSwitchRegex(action.trigger)) {
        context.addIssue({
          code: 'custom',
          path: ['actions', index, 'trigger'],
          message: 'Enter a supported metaflow pattern.',
        })
      }

      if (triggers.has(identity)) {
        context.addIssue({
          code: 'custom',
          path: ['actions', index, 'trigger'],
          message: 'Each metaflow trigger must be unique within its type.',
        })
      }

      triggers.add(identity)
      inspectChildren(action.children, ['actions', index, 'children'], 1)
    })

    if (nodeCount > 100) {
      context.addIssue({
        code: 'custom',
        path: ['actions'],
        message: 'Use no more than 100 guided metaflow nodes.',
      })
    }
  })
