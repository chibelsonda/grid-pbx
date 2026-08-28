import { z } from 'zod'

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
    if (key.label !== null && key.value === null) {
      context.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'A labeled line key requires a value.',
      })
    }

    if (key.type === 'parking' && key.value !== null) {
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

export const lineKeyFormSchema = z
  .object({ line_keys: z.array(lineKeySchema).max(100) })
  .strict()
  .superRefine(({ line_keys: lineKeys }, context) => {
    const seen = new Set<string>()

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
    })
  })
