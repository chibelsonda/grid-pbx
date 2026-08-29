import { describe, expect, it } from 'vitest'
import { invalidControlClasses, validationControlClass } from './validationStyles'

describe('validationControlClass', () => {
  it('returns the shared invalid treatment only when an error is present', () => {
    expect(validationControlClass(null)).toBe('')
    expect(validationControlClass([])).toBe('')
    expect(validationControlClass(['Enter a value.'])).toBe(invalidControlClasses)
  })
})
