import { z } from 'zod'

export const descendantOnboardingSchema = z.object({
  reference: z.string().min(1, 'Select a descendant account.'),
  confirmation: z.string().trim().min(1, 'Enter the descendant account name.'),
  acknowledge_existing_access: z.boolean().refine((value) => value, {
    message: 'Acknowledge the inherited organization access.',
  }),
})

export type DescendantOnboardingForm = z.infer<typeof descendantOnboardingSchema>
