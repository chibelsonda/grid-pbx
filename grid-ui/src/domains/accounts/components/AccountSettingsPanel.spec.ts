import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { AccountDetail } from '../types/account'
import AccountSettingsPanel from './AccountSettingsPanel.vue'

const account: AccountDetail = {
  id: '10000000-0000-4000-8000-000000000001',
  name: 'Grid Support',
  realm: 'support.example.test',
  timezone: 'Asia/Manila',
  enabled: true,
  organization: { id: '20000000-0000-4000-8000-000000000001', name: 'GridPBX' },
  resource_counts: {
    extensions: 0,
    devices: 0,
    phone_numbers: 0,
    callflows: 0,
    voicemail_boxes: 0,
    queues: 0,
    media: 0,
    recordings: 0,
  },
  configuration_boundaries: {
    identity_defaults: 'safe_fields_available',
    calling_defaults: 'safe_fields_available',
    advanced_routing: 'guided_rules_available',
    enable_disable: 'implemented_confirmed',
    billing_topup: 'provider_required',
  },
  configuration: {
    organization_name: 'Grid Corp',
    language: 'en-US',
    call_waiting_enabled: true,
    do_not_disturb_enabled: false,
    outbound_privacy: null,
    show_rate: false,
    ringtone_internal: null,
    ringtone_external: null,
    caller_id: {
      internal: { name: 'Support', number: '1000' },
      external: { name: null, phone_number_id: null, number: null, unresolved: false },
      emergency: { name: null, phone_number_id: null, number: null, unresolved: false },
    },
    call_restriction: {},
    call_recording: {},
    dial_plan: { system: [], rules: [] },
    formatters: [],
    preflow: { callflow_id: null, name: null, unresolved: false },
    metaflows: {
      binding_digit: null,
      digit_timeout: null,
      listen_on: null,
      number_flow_count: 0,
      pattern_flow_count: 0,
      actions: [],
      locked_action_count: 0,
    },
  },
  options: { caller_id_numbers: [] },
  projection: { status: 'synced', version: 1, last_synced_at: null },
  permissions: { can_manage_settings: true },
}

function mountPanel(fieldErrors: Record<string, string[]> = {}) {
  return mount(AccountSettingsPanel, {
    props: {
      account,
      saving: false,
      error: null,
      fieldErrors,
      restrictionOptions: [],
      callflowOptions: [],
      metaflowResources: { media: [], callflows: [], devices: [], extensions: [] },
      optionsError: null,
    },
    global: {
      stubs: {
        CrudSlideOver: { template: '<div><slot /></div>' },
      },
    },
  })
}

function outerTab(wrapper: ReturnType<typeof mountPanel>, name: 'Basic' | 'Advanced') {
  return wrapper.findAll('[role="tab"]').find((tab) => tab.text() === name)!
}

describe('AccountSettingsPanel', () => {
  it('separates core calling defaults from advanced policy and routing settings', async () => {
    const wrapper = mountPanel()
    const name = wrapper.get('input[aria-label="Account name"]')
    const restrictionHeading = wrapper
      .findAll('h2')
      .find((heading) => heading.text() === 'Account call restrictions')!
    const basicPanel = name.element.closest('[role="tabpanel"]') as HTMLElement
    const advancedPanel = restrictionHeading.element.closest('[role="tabpanel"]') as HTMLElement

    expect(outerTab(wrapper, 'Basic').attributes('aria-selected')).toBe('true')
    expect(basicPanel).not.toBe(advancedPanel)
    expect(basicPanel.textContent).toContain('Identity and locale')
    expect(basicPanel.textContent).toContain('Default caller identity')
    expect(advancedPanel.textContent).toContain('Call-recording defaults')
    expect(advancedPanel.textContent).toContain('Dial plan and formatters')
    expect(advancedPanel.textContent).toContain('Preflow and in-call features')

    await outerTab(wrapper, 'Advanced').trigger('click')
    expect(outerTab(wrapper, 'Advanced').attributes('aria-selected')).toBe('true')
  })

  it('returns validation to Basic and opens Advanced for advanced API errors', async () => {
    const wrapper = mountPanel()

    await outerTab(wrapper, 'Advanced').trigger('click')
    await wrapper.get('input[aria-label="Account name"]').setValue('')
    await wrapper.get('form').trigger('submit')

    expect(outerTab(wrapper, 'Basic').attributes('aria-selected')).toBe('true')
    expect(wrapper.get('input[aria-label="Account name"]').attributes('aria-invalid')).toBe('true')

    await wrapper.setProps({ fieldErrors: { call_restriction: ['Invalid policy.'] } })
    expect(outerTab(wrapper, 'Advanced').attributes('aria-selected')).toBe('true')
  })
})
