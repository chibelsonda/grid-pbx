import { describe, expect, it } from 'vitest'
import { useMenuForm } from './useMenuForm'

describe('useMenuForm', () => {
  it('normalizes empty optional values and validates the editable contract', () => {
    const { form, validate } = useMenuForm(null)
    form.name = '  Main menu  '
    form.record_pin = ''
    form.hunt_allow = ''

    const result = validate()

    expect(result.success).toBe(true)
    if (!result.success) return
    expect(result.data.name).toBe('Main menu')
    expect(result.data.record_pin).toBeNull()
    expect(result.data.hunt_allow).toBeNull()
  })

  it('reports all bounded digit collection and PIN errors', () => {
    const { form, validate } = useMenuForm(null)
    form.timeout = 60_001
    form.interdigit_timeout = 0
    form.max_extension_length = 7
    form.retries = 0
    form.record_pin = '12ab'

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(
      expect.arrayContaining([
        'name',
        'timeout',
        'interdigit_timeout',
        'max_extension_length',
        'retries',
        'record_pin',
      ]),
    )
  })
})
