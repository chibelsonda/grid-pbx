import { describe, expect, it } from 'vitest'
import { useTemporalRuleForm } from './useTemporalRuleForm'

describe('useTemporalRuleForm', () => {
  it('normalizes schema fields without exposing the operational enabled override', () => {
    const { form, validate } = useTemporalRuleForm(null)
    form.name = '  Business hours  '
    form.cycle = 'daily'

    const result = validate()

    expect(result).toEqual({
      success: true,
      data: expect.objectContaining({
        name: 'Business hours',
        cycle: 'daily',
        days: [],
        weekdays: [],
        month: null,
        ordinal: null,
      }),
      errors: {},
    })
    if (result.success) expect(result.data).not.toHaveProperty('enabled')
  })

  it('reports invalid day tokens and all bounded numeric fields', () => {
    const { daysText, form, validate } = useTemporalRuleForm(null)
    form.name = 'Monthly close'
    form.cycle = 'monthly'
    form.interval = 0
    form.time_window_start = 86_401
    daysText.value = '1, weekday, 32'

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(
      expect.arrayContaining(['interval', 'time_window_start', 'days.1', 'days.2']),
    )
  })
})
