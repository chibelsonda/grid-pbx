import { z } from 'zod'

const durationQuery = z
  .string()
  .regex(/^\d+$/)
  .refine((value) => Number(value) <= 86_400)

export const callDetailRecordDrilldownSchema = z
  .object({
    started_after: z.iso.datetime({ offset: true }),
    started_before: z.iso.datetime({ offset: true }),
    direction: z.enum(['inbound', 'outbound']).optional(),
    outcome: z.enum(['answered', 'unanswered']).optional(),
    search: z.string().trim().min(1).max(100).optional(),
    duration_min: durationQuery.optional(),
    duration_max: durationQuery.optional(),
  })
  .strict()
  .superRefine((value, context) => {
    if (Date.parse(value.started_after) >= Date.parse(value.started_before)) {
      context.addIssue({
        code: 'custom',
        path: ['started_before'],
        message: 'The precise end time must be after the precise start time.',
      })
    }

    if (
      value.duration_min !== undefined &&
      value.duration_max !== undefined &&
      Number(value.duration_min) > Number(value.duration_max)
    ) {
      context.addIssue({
        code: 'custom',
        path: ['duration_max'],
        message: 'The maximum duration must be greater than or equal to the minimum duration.',
      })
    }
  })

export type CallDetailRecordDrilldown = z.infer<typeof callDetailRecordDrilldownSchema>
