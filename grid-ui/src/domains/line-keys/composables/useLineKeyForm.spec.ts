import { describe, expect, it } from 'vitest'
import type { LineKeyPreview } from '../types/lineKey'
import { useLineKeyForm } from './useLineKeyForm'

const preview: LineKeyPreview = {
  device: {
    id: 'device-public-id',
    name: 'Reception phone',
    make: 'Yealink',
    endpoint_family: 'T5',
    model: 'T54W',
    mac_address: '00:11:22:33:44:55',
    line_keys: [],
  },
  capability: { preview_available: true, apply_available: true, reason: null },
  payload_preview: { provision: { combo_keys: {}, feature_keys: {} } },
}

describe('useLineKeyForm', () => {
  it('normalizes a labeled parking value to the typed Switch payload shape', () => {
    const { form, safePreview, validate } = useLineKeyForm(preview)
    form.push({ category: 'feature', position: 2, type: 'parking', value: '3', label: 'Slot 3' })

    expect(validate()).toEqual({
      success: true,
      data: [
        { category: 'feature', position: 2, type: 'parking', value: '3', label: 'Slot 3' },
      ],
    })
    expect(safePreview.value.provision.feature_keys).toEqual({
      2: { type: 'parking', value: { label: 'Slot 3', value: 3 } },
    })
  })

  it('reports duplicate positions and values that do not match their key type', () => {
    const { form, validate, validationErrors } = useLineKeyForm(preview)
    form.push({ category: 'combo', position: 0, type: 'speed_dial', value: 1001, label: null })
    form.push({ category: 'combo', position: 0, type: 'parking', value: 11, label: null })

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(validationErrors.value)).toEqual(
      expect.arrayContaining([
        'line_keys.0.value',
        'line_keys.1.value',
        'line_keys.1.position',
      ]),
    )
  })
})
