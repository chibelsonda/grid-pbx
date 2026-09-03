import { z } from 'zod'

export const passwordMinimumLength = 12
export const passwordMaximumLength = 128

const passwordSchema = z
  .string()
  .min(passwordMinimumLength, `Use at least ${passwordMinimumLength} characters.`)
  .max(passwordMaximumLength, `Use no more than ${passwordMaximumLength} characters.`)
  .regex(/[a-z]/, 'Include a lowercase letter.')
  .regex(/[A-Z]/, 'Include an uppercase letter.')
  .regex(/[0-9]/, 'Include a number.')
  .regex(/[^A-Za-z0-9]/, 'Include a symbol.')

export const resetPasswordFormSchema = z
  .object({
    email: z.string().trim().toLowerCase().pipe(z.email('Enter a valid email address.')),
    token: z.string().min(1, 'The password reset link is incomplete.'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'Confirm your new password.'),
  })
  .refine((input) => input.password === input.password_confirmation, {
    message: 'Passwords must match.',
    path: ['password_confirmation'],
  })

export type ResetPasswordInput = z.infer<typeof resetPasswordFormSchema>
