import { describe, expect, it } from 'vitest'
import { passwordFormSchema } from './passwordFormSchema'

describe('passwordFormSchema', () => {
  it('accepts a confirmed password that differs from the current password', () => {
    expect(
      passwordFormSchema.parse({
        current_password: 'current-secure-password',
        password: 'new-secure-password',
        password_confirmation: 'new-secure-password',
      }),
    ).toEqual({
      current_password: 'current-secure-password',
      password: 'new-secure-password',
      password_confirmation: 'new-secure-password',
    })
  })

  it('rejects a short, reused, or unconfirmed new password', () => {
    const result = passwordFormSchema.safeParse({
      current_password: 'reused-password',
      password: 'reused-password',
      password_confirmation: 'different-password',
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.flatten().fieldErrors.password).toContain(
      'Choose a new password that differs from your current password.',
    )
    expect(result.error.flatten().fieldErrors.password_confirmation).toContain(
      'The new password confirmation does not match.',
    )
  })
})
