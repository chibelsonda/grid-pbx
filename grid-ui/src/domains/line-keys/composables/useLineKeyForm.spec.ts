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
  capability: {
    preview_available: true,
    apply_available: true,
    reason: null,
    model: {
      matched: false,
      max_keys: null,
      max_expansion_modules: null,
      keys_per_expansion_module: null,
      total_keys: null,
      supported_key_types: ['line', 'presence', 'personal_parking', 'speed_dial', 'parking'],
      value_sources: [],
      manufacturer_provider: null,
    },
  },
  value_choices: [],
  payload_preview: { provision: { combo_keys: {}, feature_keys: {} } },
}

describe('useLineKeyForm', () => {
  it('normalizes a labeled parking value to the typed Switch payload shape', () => {
    const { form, safePreview, validate } = useLineKeyForm(preview)
    form.push({ category: 'feature', position: 2, type: 'parking', value: '3', label: 'Slot 3' })

    expect(validate()).toEqual({
      success: true,
      data: [{ category: 'feature', position: 2, type: 'parking', value: '3', label: 'Slot 3' }],
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
      expect.arrayContaining(['line_keys.0.value', 'line_keys.1.value', 'line_keys.1.position']),
    )
  })

  it('applies model position and key-type capabilities', () => {
    const modelPreview: LineKeyPreview = {
      ...preview,
      capability: {
        ...preview.capability,
        model: {
          ...preview.capability.model,
          matched: true,
          max_keys: 2,
          total_keys: 2,
          supported_key_types: ['line', 'presence'],
        },
      },
    }
    const { form, validate, validationErrors } = useLineKeyForm(modelPreview)
    form.push({ category: 'combo', position: 0, type: 'line', value: '1001', label: null })
    form.push({ category: 'feature', position: 0, type: 'speed_dial', value: '1002', label: null })

    expect(validate().success).toBe(false)
    expect(validationErrors.value).toMatchObject({
      'line_keys.1.position': ['Each physical model position may be assigned only once.'],
      'line_keys.1.type': ['This line-key type is not supported by the selected model.'],
    })
  })

  it('adds the first free position inside a requested hardware section', () => {
    const { add, form } = useLineKeyForm({
      ...preview,
      capability: {
        ...preview.capability,
        model: {
          ...preview.capability.model,
          matched: true,
          max_keys: 10,
          max_expansion_modules: 1,
          keys_per_expansion_module: 20,
          total_keys: 30,
          supported_key_types: ['presence'],
        },
      },
    })

    add(10, 29)
    add(10, 29)

    expect(form.map((key) => ({ position: key.position, type: key.type }))).toEqual([
      { position: 10, type: 'presence' },
      { position: 11, type: 'presence' },
    ])
  })
})
