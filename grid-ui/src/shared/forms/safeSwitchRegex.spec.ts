import { describe, expect, it } from 'vitest'
import { isSafeSwitchRegex } from './safeSwitchRegex'

describe('safe Switch regex validation', () => {
  it('accepts ordinary anchored dial-plan expressions', () => {
    expect(isSafeSwitchRegex('^([2-9][0-9]{6})$')).toBe(true)
  })

  it('rejects invalid and recursive expressions', () => {
    expect(isSafeSwitchRegex('([0-9]+')).toBe(false)
    expect(isSafeSwitchRegex('(?R)')).toBe(false)
  })
})
