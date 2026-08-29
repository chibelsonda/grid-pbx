import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import MenuFormPanel from './MenuFormPanel.vue'

describe('MenuFormPanel', () => {
  it('keeps client validation inline and marks all invalid controls', async () => {
    const wrapper = mount(MenuFormPanel, {
      props: {
        record: null,
        options: { media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        components: { ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    const inputs = wrapper.findAll('input[type="number"]')
    await inputs[0]!.setValue(60_001)
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(inputs[0]!.attributes('aria-invalid')).toBe('true')
    expect(inputs[0]!.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a menu name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })
})
