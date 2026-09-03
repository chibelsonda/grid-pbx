import { describe, expect, it } from 'vitest'
import { resetPasswordFormSchema } from './resetPasswordFormSchema'

const validInput = {
  email: 'owner@example.test',
  token: 'reset-token',
  password: 'New-password2!',
  password_confirmation: 'New-password2!',
}

describe('resetPasswordFormSchema', () => {
  it('accepts a complete strong-password payload', () => {
    expect(resetPasswordFormSchema.parse(validInput)).toEqual(validInput)
  })

  it.each([
    ['too short', 'Short1!', 'Use at least 12 characters.'],
    ['missing uppercase', 'new-password2!', 'Include an uppercase letter.'],
    ['missing lowercase', 'NEW-PASSWORD2!', 'Include a lowercase letter.'],
    ['missing number', 'New-password!', 'Include a number.'],
    ['missing symbol', 'Newpassword22', 'Include a symbol.'],
  ])('rejects a %s password', (_case, password, message) => {
    const result = resetPasswordFormSchema.safeParse({
      ...validInput,
      password,
      password_confirmation: password,
    })

    expect(result.success).toBe(false)
    expect(result.error?.flatten().fieldErrors.password).toContain(message)
  })

  it('rejects a confirmation mismatch', () => {
    const result = resetPasswordFormSchema.safeParse({
      ...validInput,
      password_confirmation: 'Different-password3!',
    })

    expect(result.success).toBe(false)
    expect(result.error?.flatten().fieldErrors.password_confirmation).toContain(
      'Passwords must match.',
    )
  })
})
