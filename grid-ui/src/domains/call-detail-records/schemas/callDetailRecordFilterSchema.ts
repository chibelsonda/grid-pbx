import { z } from 'zod'
import type { CallDetailRecordFilters } from '../types/callDetailRecord'

const dateFilter = z
  .string()
  .refine(
    (value) =>
      value === '' ||
      (/^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(`${value}T00:00:00Z`))),
    'Enter a valid date.',
  )
const durationFilter = z.preprocess(
  (value) => (typeof value === 'number' ? String(value) : value),
  z
    .string()
    .refine(
      (value) => value === '' || (/^\d+$/.test(value) && Number(value) <= 86_400),
      'Enter a duration from 0 to 86400 seconds.',
    ),
)
const preciseTimestampFilter = z
  .string()
  .refine(
    (value) => value === '' || z.iso.datetime({ offset: true }).safeParse(value).success,
    'Enter a valid timestamp with a UTC offset.',
  )

export const callDetailRecordFilterSchema: z.ZodType<CallDetailRecordFilters> = z
  .object({
    search: z.string().trim().max(100),
    direction: z.enum(['', 'inbound', 'outbound']),
    outcome: z.enum(['', 'answered', 'unanswered']),
    hangup_cause: z.string().trim().max(64),
    started_from: dateFilter,
    started_to: dateFilter,
    started_after: preciseTimestampFilter,
    started_before: preciseTimestampFilter,
    duration_min: durationFilter,
    duration_max: durationFilter,
  })
  .superRefine((input, context) => {
    if (input.started_from && input.started_to && input.started_from > input.started_to) {
      context.addIssue({
        code: 'custom',
        path: ['started_to'],
        message: 'The end date must be on or after the start date.',
      })
    }

    if (
      input.started_after &&
      input.started_before &&
      Date.parse(input.started_after) >= Date.parse(input.started_before)
    ) {
      context.addIssue({
        code: 'custom',
        path: ['started_before'],
        message: 'The precise end time must be after the precise start time.',
      })
    }

    if (
      input.duration_min &&
      input.duration_max &&
      Number(input.duration_min) > Number(input.duration_max)
    ) {
      context.addIssue({
        code: 'custom',
        path: ['duration_max'],
        message: 'The maximum duration must be greater than or equal to the minimum duration.',
      })
    }
  })
