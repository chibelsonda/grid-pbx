import { describe, expect, it } from 'vitest'
import { profileFormSchema } from './profileFormSchema'

describe('profileFormSchema', () => {
  it('trims a valid display name', () => {
    expect(profileFormSchema.parse({ name: '  Operations Admin  ' })).toEqual({
      name: 'Operations Admin',
    })
  })

  it('rejects empty and overlong display names', () => {
    expect(profileFormSchema.safeParse({ name: '   ' }).success).toBe(false)
    expect(profileFormSchema.safeParse({ name: 'a'.repeat(256) }).success).toBe(false)
  })
})
