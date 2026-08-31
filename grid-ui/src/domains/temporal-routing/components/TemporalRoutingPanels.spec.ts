import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TemporalRulePanel from './TemporalRulePanel.vue'
import TemporalRuleSetPanel from './TemporalRuleSetPanel.vue'

const shared = {
  saving: false,
  error: null,
  fieldErrors: {},
  canManage: true,
}

const stubs = {
  CrudSlideOver: { template: '<div><slot /></div>' },
  ConfirmDialog: true,
  TemporalEffectiveStatus: true,
}

describe('temporal routing panels', () => {
  it('uses inline Zod errors and the shared red invalid-control treatment for rules', async () => {
    const wrapper = mount(TemporalRulePanel, {
      props: { ...shared, record: null },
      global: { stubs },
    })

    expect(wrapper.findAll('[role="tab"]')).toHaveLength(0)
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a rule name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })

  it('marks the rule-set name and selection group without a duplicate global alert', async () => {
    const wrapper = mount(TemporalRuleSetPanel, {
      props: { ...shared, record: null, options: { rules: [] } },
      global: { stubs },
    })

    expect(wrapper.findAll('[role="tab"]')).toHaveLength(0)
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    const selection = wrapper.get('[aria-invalid="true"]:not(input)')
    expect(name.classes()).toContain('!border-red-400')
    expect(selection.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Select at least one schedule rule.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })
})
