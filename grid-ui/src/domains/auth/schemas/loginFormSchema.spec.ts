import { describe, expect, it } from 'vitest'
import { loginFormSchema } from './loginFormSchema'

describe('loginFormSchema', () => {
  it('accepts the Laravel login payload shape', () => {
    expect(
      loginFormSchema.parse({
        email: 'admin@gridpbx.local',
        password: 'admin-change-me',
        remember: true,
      }),
    ).toEqual({
      email: 'admin@gridpbx.local',
      password: 'admin-change-me',
      remember: true,
    })
  })

  it('rejects malformed credentials before the request', () => {
    const result = loginFormSchema.safeParse({
      email: 'not-an-email',
      password: '',
      remember: true,
    })
    const fieldErrors = result.success ? {} : result.error.flatten().fieldErrors

    expect(result.success).toBe(false)
    expect(fieldErrors.email).toContain('Enter a valid email address.')
    expect(fieldErrors.password).toContain('Enter your password.')
  })
})
