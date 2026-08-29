import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BlacklistFormPanel from './BlacklistFormPanel.vue'

describe('BlacklistFormPanel', () => {
  it('uses inline Zod errors and marks invalid controls red', async () => {
    const wrapper = mount(BlacklistFormPanel, {
      props: {
        record: null,
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

    await wrapper.get('textarea[aria-label="Blocked caller numbers"]').setValue('555-0100')
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Blacklist name"]')
    const numbers = wrapper.get('textarea[aria-label="Blocked caller numbers"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(numbers.attributes('aria-invalid')).toBe('true')
    expect(numbers.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a blacklist name.')
    expect(wrapper.text()).toContain('Use E.164 format for: 555-0100')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })
})
