import { describe, expect, it } from 'vitest'
import { forgotPasswordFormSchema } from './forgotPasswordFormSchema'

describe('forgotPasswordFormSchema', () => {
  it('normalizes a valid email address', () => {
    expect(forgotPasswordFormSchema.parse({ email: ' OWNER@EXAMPLE.TEST ' })).toEqual({
      email: 'owner@example.test',
    })
  })

  it('rejects a malformed email address', () => {
    const result = forgotPasswordFormSchema.safeParse({ email: 'not-an-email' })

    expect(result.success).toBe(false)
    expect(result.error?.flatten().fieldErrors.email).toContain('Enter a valid email address.')
  })
})
