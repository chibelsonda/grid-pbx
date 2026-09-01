import { z } from 'zod'

export const profileFormSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Enter your display name.')
    .max(255, 'Display names may not exceed 255 characters.'),
})

export type ProfileInput = z.infer<typeof profileFormSchema>
