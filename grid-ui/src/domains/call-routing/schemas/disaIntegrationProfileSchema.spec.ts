import { describe, expect, it } from 'vitest'
import { disaIntegrationProfileSchema } from './disaIntegrationProfileSchema'

function validInput() {
  return {
    name: 'After-hours access',
    is_active: true,
    pin: '82736491',
    pin_confirmation: '82736491',
    retries: 2,
    interdigit_ms: 3000,
    max_digits: 15,
    preconnect_audio: 'dialtone',
  }
}

describe('disaIntegrationProfileSchema', () => {
  it('accepts a bounded native DISA policy', () => {
    expect(disaIntegrationProfileSchema.safeParse(validInput()).success).toBe(true)
  })

  it('rejects weak credentials and unbounded controls', () => {
    const result = disaIntegrationProfileSchema.safeParse({
      ...validInput(),
      pin: '1234',
      pin_confirmation: '1234',
      retries: 4,
      interdigit_ms: 9000,
      max_digits: 32,
      preconnect_audio: 'custom',
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
        expect.arrayContaining([
          'pin',
          'retries',
          'interdigit_ms',
          'max_digits',
          'preconnect_audio',
        ]),
      )
    }
  })

  it('rejects a mismatched PIN confirmation', () => {
    const result = disaIntegrationProfileSchema.safeParse({
      ...validInput(),
      pin_confirmation: '99999999',
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toContain('pin_confirmation')
    }
  })

  it('rejects browser-controlled native security flags', () => {
    expect(
      disaIntegrationProfileSchema.safeParse({
        ...validInput(),
        enforce_call_restriction: false,
      }).success,
    ).toBe(false)
  })
})
