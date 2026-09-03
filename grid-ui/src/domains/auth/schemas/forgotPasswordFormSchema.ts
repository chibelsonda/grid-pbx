import { z } from 'zod'

export const forgotPasswordFormSchema = z.object({
  email: z.string().trim().toLowerCase().pipe(z.email('Enter a valid email address.')),
})

export type ForgotPasswordInput = z.infer<typeof forgotPasswordFormSchema>
