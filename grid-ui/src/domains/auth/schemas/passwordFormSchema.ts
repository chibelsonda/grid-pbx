import { z } from 'zod'

export const passwordFormSchema = z
  .object({
    current_password: z
      .string()
      .min(1, 'Enter your current password.')
      .max(1024, 'The current password may not exceed 1024 characters.'),
    password: z
      .string()
      .min(12, 'Use at least 12 characters for your new password.')
      .max(1024, 'The new password may not exceed 1024 characters.'),
    password_confirmation: z
      .string()
      .min(1, 'Confirm your new password.')
      .max(1024, 'The password confirmation may not exceed 1024 characters.'),
  })
  .refine((input) => input.password !== input.current_password, {
    message: 'Choose a new password that differs from your current password.',
    path: ['password'],
  })
  .refine((input) => input.password === input.password_confirmation, {
    message: 'The new password confirmation does not match.',
    path: ['password_confirmation'],
  })

export type PasswordInput = z.infer<typeof passwordFormSchema>
