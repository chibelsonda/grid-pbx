import { describe, expect, it } from 'vitest'
import { callerIdListFormSchema } from './callerIdListFormSchema'

const list = (entry: Record<string, unknown>) => ({
  name: 'VIP callers',
  description: '',
  organization: '',
  entries: [{ id: null, display_name: '', number: null, pattern: null, ...entry }],
})

describe('callerIdListFormSchema', () => {
  it('accepts number prefixes and normalizes optional text', () => {
    const result = callerIdListFormSchema.safeParse(list({ number: '+1555' }))

    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.description).toBeNull()
      expect(result.data.entries[0]?.display_name).toBeNull()
      expect(result.data.entries[0]?.number).toBe('+1555')
    }
  })

  it('requires exactly one safe match value', () => {
    expect(
      callerIdListFormSchema.safeParse(list({ number: '+1555', pattern: '^1555' })).success,
    ).toBe(false)
    expect(callerIdListFormSchema.safeParse(list({ pattern: '(?R)' })).success).toBe(false)
  })
})
