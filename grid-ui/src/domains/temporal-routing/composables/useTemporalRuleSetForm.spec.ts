import { describe, expect, it } from 'vitest'
import { useTemporalRuleSetForm } from './useTemporalRuleSetForm'

const first = '11111111-1111-4111-8111-111111111111'
const second = '22222222-2222-4222-8222-222222222222'

describe('useTemporalRuleSetForm', () => {
  it('requires a name and at least one rule', () => {
    const { validate } = useTemporalRuleSetForm(null)
    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(expect.arrayContaining(['name', 'rule_ids']))
  })

  it('preserves and reorders the selected public rule identifiers', () => {
    const { form, moveRule, validate } = useTemporalRuleSetForm(null)
    form.name = '  Office schedule  '
    form.rule_ids = [first, second]
    moveRule(second, -1)

    expect(validate()).toEqual({
      success: true,
      data: { name: 'Office schedule', rule_ids: [second, first] },
      errors: {},
    })
  })
})
