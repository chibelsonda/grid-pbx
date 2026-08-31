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

  it('maps prompt suppression to the values consumed by the installed runtime', () => {
    const { form, validate } = useMenuForm(null)
    form.name = 'Main menu'
    form.suppress_media = true
    form.invalid_media_enabled = true
    form.invalid_media_id = '8472d3d5-c79f-4ab1-8c9e-d738f7b03953'
    form.transfer_media_enabled = true
    form.transfer_media_id = '11289f55-aa15-4edf-aeca-a0acfd5eb21b'

    const result = validate()

    expect(result.success).toBe(true)
    if (!result.success) return
    expect(result.data.invalid_media_enabled).toBe(false)
    expect(result.data.invalid_media_id).toBeNull()
    expect(result.data.transfer_media_enabled).toBe(false)
    expect(result.data.transfer_media_id).toBeNull()
    expect(result.data.exit_media_enabled).toBe(false)
  })
})
