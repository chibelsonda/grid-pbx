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

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.find('input[aria-label="Initial digit timeout (ms)"]').isVisible()).toBe(false)
    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    await wrapper.get('input[aria-label="Initial digit timeout (ms)"]').setValue(60_001)
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a menu name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')

    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    const timeout = wrapper.get('input[aria-label="Initial digit timeout (ms)"]')
    expect(timeout.attributes('aria-invalid')).toBe('true')
    expect(timeout.classes()).toContain('!border-red-400')
  })

  it('submits runtime prompt suppression through the shared toggle', async () => {
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
    await wrapper.get('input[aria-label="Name"]').setValue('Main menu')
    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    const suppress = wrapper
      .findAllComponents(ToggleSwitch)
      .find((toggle) => toggle.props('label') === 'Suppress result prompts')

    expect(suppress).toBeDefined()
    await suppress!.vm.$emit('update:modelValue', true)
    await wrapper.get('form').trigger('submit')

    const input = wrapper.emitted('save')?.[0]?.[0]
    expect(input).toMatchObject({
      suppress_media: true,
      invalid_media_enabled: false,
      transfer_media_enabled: false,
      exit_media_enabled: false,
    })
  })
})
