import { describe, expect, it } from 'vitest'
import { z } from 'zod'
import { nullableInteger, validateForm } from './zod'

describe('validateForm', () => {
  it('returns parsed data for a valid form', () => {
    const result = validateForm(z.object({ name: z.string().trim().min(1) }), { name: ' Desk ' })

    expect(result).toEqual({ success: true, data: { name: 'Desk' }, errors: {} })
  })

  it('maps Zod issues to Laravel-compatible dotted field errors', () => {
    const result = validateForm(
      z.object({ sip: z.object({ password: z.string().min(12, 'Use at least 12 characters.') }) }),
      { sip: { password: 'short' } },
    )

    expect(result.success).toBe(false)
    expect(result.errors).toEqual({ 'sip.password': ['Use at least 12 characters.'] })
  })

  it('normalizes cleared optional integer controls to null', () => {
    expect(nullableInteger(0, 3600).parse('')).toBeNull()
    expect(nullableInteger(0, 3600).parse(30)).toBe(30)
  })
})
