import { describe, expect, it } from 'vitest'
import { z } from 'zod'
import { validateForm } from './zod'

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
})
