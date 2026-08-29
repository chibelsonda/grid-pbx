import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import DirectoryFormPanel from './DirectoryFormPanel.vue'

describe('DirectoryFormPanel', () => {
  it('keeps client validation inline and marks the owning controls invalid', async () => {
    const wrapper = mount(DirectoryFormPanel, {
      props: {
        record: null,
        options: { extensions: [] },
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

    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[required]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a directory name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })
})
