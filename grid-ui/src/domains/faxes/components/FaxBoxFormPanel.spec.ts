import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FaxBoxFormPanel from './FaxBoxFormPanel.vue'

describe('FaxBoxFormPanel', () => {
  it('keeps Zod errors inline and marks each invalid native control red', async () => {
    const wrapper = mount(FaxBoxFormPanel, {
      props: {
        record: null,
        options: {
          owners: [],
          caller_id_numbers: [],
          timezones: ['UTC'],
          account_defaults: { timezone: 'UTC' },
        },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.find('input[aria-label="Fax retries"]').isVisible()).toBe(false)
    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    await wrapper.get('input[aria-label="Fax retries"]').setValue('5')
    await wrapper.findAll('[role="tab"]')[0]!.trigger('click')
    await wrapper.get('input[aria-label="Inbound notification emails"]').setValue('invalid')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.findAll('[role="tab"]')[0]!.attributes('aria-selected')).toBe('true')
    const name = wrapper.get('input[aria-label="Fax-box name"]')
    const retries = wrapper.get('input[aria-label="Fax retries"]')
    const inbound = wrapper.get('input[aria-label="Inbound notification emails"]')
    for (const control of [name, retries, inbound]) {
      expect(control.attributes('aria-invalid')).toBe('true')
      expect(control.classes()).toContain('!border-red-400')
    }
    expect(wrapper.text()).toContain('Enter a fax-box name.')
    expect(wrapper.text()).toContain('Enter a valid email address.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })
})
