import { z } from 'zod'
import type { RecordingFilters } from '../types/recording'

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

export const recordingFilterSchema: z.ZodType<RecordingFilters> = z
  .object({
    search: z.string().trim().max(100),
    direction: z.enum(['', 'inbound', 'outbound']),
    started_from: dateFilter,
    started_to: dateFilter,
    duration_min: durationFilter,
    duration_max: durationFilter,
    has_audio: z.enum(['', '1', '0']),
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
