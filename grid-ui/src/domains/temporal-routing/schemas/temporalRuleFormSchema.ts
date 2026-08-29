import { z } from 'zod'

const weekdays = z.enum([
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday',
  'sunday',
])

const unique = <T>(values: T[]): boolean => new Set(values).size === values.length

export const temporalRuleFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a rule name.').max(128),
    cycle: z.enum(['date', 'daily', 'weekly', 'monthly', 'yearly']),
    interval: z.number().int().min(1, 'Use an interval of at least 1.'),
    start_date: z
      .string()
      .regex(/^\d{4}-\d{2}-\d{2}$/, 'Use a valid start date.')
      .nullable(),
    time_window_start: z.number().int().min(0).max(86_400).nullable(),
    time_window_stop: z.number().int().min(0).max(86_400).nullable(),
    days: z
      .array(z.number({ error: 'Use day numbers from 1 through 31.' }).int().min(1).max(31))
      .refine(unique, 'Enter each day once.'),
    weekdays: z.array(weekdays).refine(unique, 'Select each weekday once.'),
    month: z.number().int().min(1).max(12).nullable(),
    ordinal: z.enum(['every', 'first', 'second', 'third', 'fourth', 'fifth', 'last']).nullable(),
  })
  .strict()

export const temporalRuleSetFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a rule set name.').max(128),
    rule_ids: z
      .array(z.uuid())
      .min(1, 'Select at least one schedule rule.')
      .refine(unique, 'Select each schedule rule once.'),
  })
  .strict()
