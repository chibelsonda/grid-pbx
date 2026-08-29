import { describe, expect, it } from 'vitest'
import { useBlacklistForm } from './useBlacklistForm'

describe('useBlacklistForm', () => {
  it('trims the name and normalizes repeated number input', () => {
    const { form, validate } = useBlacklistForm(null)
    form.name = '  Known spam  '
    form.numbersText = '+15550001000\n+15550001000, +15550001001'

    expect(validate()).toEqual({
      success: true,
      data: {
        name: 'Known spam',
        should_block_anonymous: false,
        is_active: false,
        numbers: ['+15550001000', '+15550001001'],
      },
      errors: {},
    })
  })

  it('reports name and E.164 validation errors inline', () => {
    const { form, validate } = useBlacklistForm(null)
    form.numbersText = '555-0100'

    const result = validate()

    expect(result.success).toBe(false)
    expect(result.errors.name).toEqual(['Enter a blacklist name.'])
    expect(result.errors['numbers.0']).toEqual(['Use E.164 format, for example +15550001000.'])
  })
})
