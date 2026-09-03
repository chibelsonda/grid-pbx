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
      catalog_available: false,
      catalog_reason: 'Provisioning catalog discovery is not configured.',
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

  it('builds valid Switch payloads for all five supported line-key types', () => {
    const extensionId = '0199a271-62c6-72cd-b726-dfdfdcebf23d'
    const { form, safePreview, validate } = useLineKeyForm(preview)
    form.push(
      { category: 'combo', position: 0, type: 'line', value: null, label: null },
      { category: 'feature', position: 1, type: 'presence', value: extensionId, label: 'Alice' },
      {
        category: 'feature',
        position: 2,
        type: 'personal_parking',
        value: extensionId,
        label: 'Park Alice',
      },
      {
        category: 'feature',
        position: 3,
        type: 'speed_dial',
        value: '+15551234567',
        label: 'Support',
      },
      { category: 'feature', position: 4, type: 'parking', value: 3, label: 'Park 3' },
    )

    expect(validate().success).toBe(true)
    expect(safePreview.value.provision).toEqual({
      combo_keys: { 0: { type: 'line' } },
      feature_keys: {
        1: { type: 'presence', value: { label: 'Alice', value: extensionId } },
        2: {
          type: 'personal_parking',
          value: { label: 'Park Alice', value: extensionId },
        },
        3: {
          type: 'speed_dial',
          value: { label: 'Support', value: '+15551234567' },
        },
        4: { type: 'parking', value: { label: 'Park 3', value: 3 } },
      },
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

  it('rejects duplicate physical positions when model metadata is unavailable', () => {
    const { form, validate, validationErrors } = useLineKeyForm(preview)
    form.push({ category: 'combo', position: 3, type: 'line', value: null, label: null })
    form.push({ category: 'feature', position: 3, type: 'speed_dial', value: '1001', label: null })

    expect(validate().success).toBe(false)
    expect(validationErrors.value).toMatchObject({
      'line_keys.1.position': ['Each physical model position may be assigned only once.'],
    })
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
    form.push({ category: 'combo', position: 0, type: 'line', value: null, label: null })
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

  it('orders assignments by physical position without changing their form indexes', () => {
    const { form, orderedAssignments } = useLineKeyForm(preview)
    form.push(
      { category: 'combo', position: 0, type: 'line', value: null, label: null },
      { category: 'combo', position: 3, type: 'presence', value: null, label: null },
      { category: 'feature', position: 1, type: 'presence', value: null, label: null },
      { category: 'feature', position: 2, type: 'presence', value: null, label: null },
    )

    expect(
      orderedAssignments.value.map(({ index, key }) => ({ index, position: key.position })),
    ).toEqual([
      { index: 0, position: 0 },
      { index: 2, position: 1 },
      { index: 3, position: 2 },
      { index: 1, position: 3 },
    ])
  })

  it('requires line appearances to be value-less combo keys', () => {
    const { form, validate, validationErrors } = useLineKeyForm(preview)
    form.push({
      category: 'feature',
      position: 0,
      type: 'line',
      value: '1001',
      label: 'Primary line',
    })

    expect(validate().success).toBe(false)
    expect(validationErrors.value).toMatchObject({
      'line_keys.0.category': ['Line appearances must use combo keys.'],
      'line_keys.0.value': ['Line appearances do not accept a value.'],
      'line_keys.0.label': ['Line appearances do not accept a label.'],
    })
  })

  it('requires account-scoped extension identifiers for presence keys', () => {
    const { form, validate, validationErrors } = useLineKeyForm(preview)
    form.push({
      category: 'feature',
      position: 0,
      type: 'presence',
      value: '1001',
      label: 'Reception',
    })

    expect(validate().success).toBe(false)
    expect(validationErrors.value).toMatchObject({
      'line_keys.0.value': ['Select an extension from this account.'],
    })
  })

  it('normalizes legacy line values before editing', () => {
    const { form, safePreview, validate } = useLineKeyForm({
      ...preview,
      device: {
        ...preview.device,
        line_keys: [
          {
            id: 'line-key-public-id',
            category: 'combo',
            position: 0,
            type: 'line',
            value: 'legacy-account-line',
            label: 'Legacy line',
          },
        ],
      },
    })

    expect(form[0]).toMatchObject({ value: null, label: null })
    expect(validate().success).toBe(true)
    expect(safePreview.value.provision.combo_keys).toEqual({ 0: { type: 'line' } })
  })
})
