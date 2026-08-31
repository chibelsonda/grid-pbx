import { z } from 'zod'
import type { LineKeyCapability } from '../types/lineKey'

const keyTypes = ['line', 'presence', 'personal_parking', 'speed_dial', 'parking'] as const

const lineKeySchema = z
  .object({
    category: z.enum(['combo', 'feature']),
    position: z.number().int().min(0).max(999),
    type: z.enum(keyTypes),
    value: z.union([z.string().trim().max(255), z.number().int()]).nullable(),
    label: z.string().trim().max(255).nullable(),
  })
  .strict()
  .superRefine((key, context) => {
    if (key.type === 'line') {
      if (key.category !== 'combo') {
        context.addIssue({
          code: 'custom',
          path: ['category'],
          message: 'Line appearances must use combo keys.',
        })
      }

      if (key.value !== null) {
        context.addIssue({
          code: 'custom',
          path: ['value'],
          message: 'Line appearances do not accept a value.',
        })
      }

      if (key.label !== null) {
        context.addIssue({
          code: 'custom',
          path: ['label'],
          message: 'Line appearances do not accept a label.',
        })
      }

      return
    }

    if (key.label !== null && key.value === null) {
      context.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'A labeled line key requires a value.',
      })
    }

    if (key.value === null || key.value === '') {
      context.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'The selected line-key type requires a value.',
      })

      return
    }

    if (
      (key.type === 'presence' || key.type === 'personal_parking') &&
      (typeof key.value !== 'string' || !z.uuid().safeParse(key.value).success)
    ) {
      context.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'Select an extension from this account.',
      })
    }

    if (key.type === 'speed_dial' && typeof key.value !== 'string') {
      context.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'Enter a dialable destination.',
      })
    }

    if (key.type === 'parking') {
      const parkingPosition = Number(key.value)

      if (!Number.isInteger(parkingPosition) || parkingPosition < 1 || parkingPosition > 10) {
        context.addIssue({
          code: 'custom',
          path: ['value'],
          message: 'Use a parking position from 1 to 10.',
        })
      }
    } else if (typeof key.value === 'number') {
      context.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'Non-parking line-key values must be text.',
      })
    }
  })

export function createLineKeyFormSchema(capability?: LineKeyCapability) {
  const totalKeys = capability?.model.total_keys ?? null
  const maximumAssignments = totalKeys === null ? 100 : Math.min(totalKeys, 1000)
  const supportedTypes = new Set(capability?.model.supported_key_types ?? keyTypes)

  return z
    .object({ line_keys: z.array(lineKeySchema).max(maximumAssignments) })
    .strict()
    .superRefine(({ line_keys: lineKeys }, context) => {
      const seen = new Set<string>()
      const physicalPositions = new Set<number>()

      lineKeys.forEach((key, index) => {
        const identity = `${key.category}:${key.position}`

        if (seen.has(identity)) {
          context.addIssue({
            code: 'custom',
            path: ['line_keys', index, 'position'],
            message: 'Each category and position combination must be unique.',
          })
        }

        seen.add(identity)

        if (capability?.model.matched && physicalPositions.has(key.position)) {
          context.addIssue({
            code: 'custom',
            path: ['line_keys', index, 'position'],
            message: 'Each physical model position may be assigned only once.',
          })
        }

        physicalPositions.add(key.position)

        if (totalKeys !== null && key.position >= totalKeys) {
          context.addIssue({
            code: 'custom',
            path: ['line_keys', index, 'position'],
            message:
              totalKeys === 0
                ? 'The selected model does not expose programmable line keys.'
                : `Use a position from 0 to ${totalKeys - 1}.`,
          })
        }

        if (!supportedTypes.has(key.type)) {
          context.addIssue({
            code: 'custom',
            path: ['line_keys', index, 'type'],
            message: 'This line-key type is not supported by the selected model.',
          })
        }
      })
    })
}

export const lineKeyFormSchema = createLineKeyFormSchema()
