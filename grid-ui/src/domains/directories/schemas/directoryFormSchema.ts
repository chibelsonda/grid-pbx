import { z } from 'zod'

const distinctStrings = (values: string[]): boolean => new Set(values).size === values.length

export const directoryFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a directory name.').max(128),
    confirm_match: z.boolean(),
    min_dtmf: z.number().int().min(1).max(20),
    max_dtmf: z.number().int().min(0).max(20),
    sort_by: z.enum(['first_name', 'last_name']),
    member_ids: z.array(z.uuid()).refine(distinctStrings, 'Select each extension once.'),
  })
  .strict()
