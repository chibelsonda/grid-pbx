import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import QueueFormPanel from './QueueFormPanel.vue'

describe('QueueFormPanel', () => {
  it('keeps client validation inline and marks invalid controls', async () => {
    const wrapper = mount(QueueFormPanel, {
      props: {
        record: null,
        options: { agents: [], media: [] },
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

    await wrapper.get('input[type="number"]').setValue(0)
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a queue name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })
})
