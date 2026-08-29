import { describe, expect, it } from 'vitest'
import { createCallflowFormSchema } from './callflowFormSchema'
import { callflowMenuBranchKeys, type CallflowEditor } from '../types/callRouting'

const destinationId = '16f95ac5-243c-476a-b238-9f51108f82e1'
const menuId = 'c48df137-8660-405d-bb64-eec23c394129'
const temporalRuleSetId = 'd5149b3a-a4f9-4b68-b970-d1657886e92e'
const temporalRuleId = '24af1546-200c-4431-8f96-e05aadd75569'
const secondTemporalRuleId = 'c927fca2-86d3-4fe8-b1e7-e575c492ad0b'
const phoneNumberId = '1078f5f7-a8c4-4296-abf8-610612cac312'

function editor(mode: 'create' | 'update'): CallflowEditor {
  return {
    mode,
    editable: true,
    blocked_reason: null,
    fallback: { editable: true, blocked_reason: null, target: null },
    menu_branches: {
      editable: true,
      blocked_reason: null,
      branches: callflowMenuBranchKeys.map((key) => ({
        key,
        label: key === 'timeout' ? 'Timeout' : key === '*' ? 'Star' : key,
        editable: true,
        blocked_reason: null,
        target: null,
      })),
      legacy_hash_present: false,
      unknown_branch_keys: [],
    },
    temporal_match: {
      editable: true,
      blocked_reason: null,
      target: null,
      preserved_branch_count: 0,
    },
    direct_temporal_routes: [],
    temporal_rule_sets: {
      [temporalRuleSetId]: [{ id: temporalRuleId, label: 'Weekdays', position: 0, resolved: true }],
    },
    temporal_rules: [
      { id: temporalRuleId, label: 'Weekdays', detail: 'Weekly recurrence' },
      { id: secondTemporalRuleId, label: 'Holidays', detail: 'Yearly recurrence' },
    ],
    destination_types: [
      { value: 'extension', label: 'Extension' },
      { value: 'menu', label: 'Menu / IVR' },
      { value: 'temporal_rule_set', label: 'Business Hours / Schedule' },
    ],
    destinations: {
      extension: [{ id: destinationId, label: 'Reception', detail: '1001' }],
      device: [],
      voicemail: [],
      callflow: [],
      media: [],
      directory: [],
      group: [],
      queue: [],
      menu: [{ id: menuId, label: 'Main IVR', detail: 'Interactive voice menu' }],
      conference: [],
      fax_box: [],
      temporal_rule_set: [
        { id: temporalRuleSetId, label: 'Office hours', detail: '1 schedule rule' },
      ],
      temporal_rules: [],
    },
    phone_numbers: [
      {
        id: phoneNumberId,
        number: '+15551234567',
        state: 'in_service',
        selected: false,
        available: true,
        assigned_callflow: null,
      },
    ],
  }
}

describe('callflow form schema', () => {
  it('requires a projected destination and a phone number when creating a route', () => {
    const result = createCallflowFormSchema(editor('create')).safeParse({
      name: '',
      destination_type: 'extension',
      destination_id: '11111111-1111-4111-8111-111111111111',
      manage_fallback: true,
      fallback_enabled: false,
      fallback_destination_type: 'voicemail',
      fallback_destination_id: '',
      manage_menu_branches: true,
      menu_branches: [],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      phone_number_ids: [],
    })

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.flatten().fieldErrors.name).toEqual(['Enter a route name.'])
    expect(result.error.flatten().fieldErrors.destination_id).toEqual([
      'Select an available destination.',
    ])
    expect(result.error.flatten().fieldErrors.phone_number_ids).toEqual([
      'Select at least one phone number.',
    ])
  })

  it('allows an update to clear projected phone-number assignments', () => {
    expect(
      createCallflowFormSchema(editor('update')).safeParse({
        name: 'Reception',
        destination_type: 'extension',
        destination_id: destinationId,
        manage_fallback: true,
        fallback_enabled: false,
        fallback_destination_type: 'voicemail',
        fallback_destination_id: '',
        manage_menu_branches: true,
        menu_branches: [],
        manage_temporal_match: true,
        temporal_match_enabled: true,
        temporal_match_destination_type: 'extension',
        temporal_match_destination_id: destinationId,
        phone_number_ids: [],
      }).success,
    ).toBe(true)
  })

  it('requires an account-scoped target only when the fallback is enabled', () => {
    const result = createCallflowFormSchema(editor('update')).safeParse({
      name: 'Reception',
      destination_type: 'extension',
      destination_id: destinationId,
      manage_fallback: true,
      fallback_enabled: true,
      fallback_destination_type: 'voicemail',
      fallback_destination_id: destinationId,
      manage_menu_branches: true,
      menu_branches: [],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      phone_number_ids: [],
    })

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.flatten().fieldErrors.fallback_destination_id).toEqual([
      'Select an available fallback destination.',
    ])
  })

  it('accepts unique account-scoped Menu key routes and rejects unavailable targets', () => {
    const schema = createCallflowFormSchema(editor('create'))
    const valid = schema.safeParse({
      name: 'Main IVR route',
      destination_type: 'menu',
      destination_id: menuId,
      manage_fallback: true,
      fallback_enabled: false,
      fallback_destination_type: 'extension',
      fallback_destination_id: '',
      manage_menu_branches: true,
      menu_branches: [{ key: '1', destination_type: 'extension', destination_id: destinationId }],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      phone_number_ids: [phoneNumberId],
    })

    expect(valid.success).toBe(true)

    const invalid = schema.safeParse({
      name: 'Main IVR route',
      destination_type: 'menu',
      destination_id: menuId,
      manage_fallback: true,
      fallback_enabled: false,
      fallback_destination_type: 'extension',
      fallback_destination_id: '',
      manage_menu_branches: true,
      menu_branches: [
        { key: '1', destination_type: 'extension', destination_id: menuId },
        { key: '1', destination_type: 'extension', destination_id: destinationId },
      ],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      phone_number_ids: [phoneNumberId],
    })

    expect(invalid.success).toBe(false)
    if (invalid.success) return
    expect(invalid.error.issues.map(({ message }) => message)).toContain(
      'Route each Menu key only once.',
    )
    expect(invalid.error.issues.map(({ message }) => message)).toContain(
      'Select an available key destination.',
    )
  })

  it('maps a Rule Set to one Switch match branch and validates its public target', () => {
    const schema = createCallflowFormSchema(editor('create'))
    const valid = schema.safeParse({
      name: 'Office hours route',
      destination_type: 'temporal_rule_set',
      destination_id: temporalRuleSetId,
      manage_fallback: true,
      fallback_enabled: false,
      fallback_destination_type: 'extension',
      fallback_destination_id: '',
      manage_menu_branches: true,
      menu_branches: [],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      phone_number_ids: [phoneNumberId],
    })

    expect(valid.success).toBe(true)
    if (!valid.success) return
    expect(valid.data).toMatchObject({
      manage_temporal_match: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      manage_menu_branches: false,
    })

    const invalid = schema.safeParse({
      name: 'Office hours route',
      destination_type: 'temporal_rule_set',
      destination_id: temporalRuleSetId,
      manage_fallback: true,
      fallback_enabled: false,
      fallback_destination_type: 'extension',
      fallback_destination_id: '',
      manage_menu_branches: false,
      menu_branches: [],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: menuId,
      phone_number_ids: [phoneNumberId],
    })

    expect(invalid.success).toBe(false)
    if (invalid.success) return
    expect(invalid.error.flatten().fieldErrors.temporal_match_destination_id).toEqual([
      'Select an available schedule match destination.',
    ])
  })

  it('preserves the selected order for direct Temporal Rules', () => {
    const schema = createCallflowFormSchema(editor('create'))
    const result = schema.safeParse({
      name: 'Direct business hours',
      destination_type: 'temporal_rules',
      destination_id: '',
      temporal_rule_ids: [secondTemporalRuleId, temporalRuleId],
      temporal_rule_routes: [
        {
          rule_id: secondTemporalRuleId,
          destination_type: 'extension',
          destination_id: destinationId,
        },
        {
          rule_id: temporalRuleId,
          destination_type: 'menu',
          destination_id: menuId,
        },
      ],
      manage_fallback: true,
      fallback_enabled: false,
      fallback_destination_type: 'extension',
      fallback_destination_id: '',
      manage_menu_branches: false,
      menu_branches: [],
      manage_temporal_match: true,
      temporal_match_enabled: true,
      temporal_match_destination_type: 'extension',
      temporal_match_destination_id: destinationId,
      phone_number_ids: [phoneNumberId],
    })

    expect(result.success).toBe(true)
    if (!result.success) return
    expect(result.data.destination_id).toBeNull()
    expect(result.data.temporal_rule_ids).toEqual([secondTemporalRuleId, temporalRuleId])
    expect(result.data.temporal_rule_routes).toEqual([
      {
        rule_id: secondTemporalRuleId,
        destination_type: 'extension',
        destination_id: destinationId,
      },
      {
        rule_id: temporalRuleId,
        destination_type: 'menu',
        destination_id: menuId,
      },
    ])
    expect(result.data.manage_temporal_match).toBe(false)
  })
})
