import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormSelect from '@/shared/components/FormSelect.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import ExtensionCreatePanel from './ExtensionCreatePanel.vue'
import DeviceDraftForm from '@/domains/devices/components/DeviceDraftForm.vue'
import { defaultExtensionFormOptions } from '../extensionForm'

describe('ExtensionCreatePanel', () => {
  it('shows inline invalid controls without a duplicate validation alert', async () => {
    const wrapper = mount(ExtensionCreatePanel, {
      props: {
        saving: false,
        error: null,
        fieldErrors: {},
        options: defaultExtensionFormOptions(),
      },
      global: {
        components: { FormSelect, ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
        },
      },
    })

    await wrapper.get('form').trigger('submit')

    const firstName = wrapper.get('input[required][maxlength="128"]')
    expect(firstName.attributes('aria-invalid')).toBe('true')
    expect(firstName.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a first name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
    expect(wrapper.emitted('save')).toBeUndefined()
  })

  it('keeps non-field API failures in the global alert', () => {
    const wrapper = mount(ExtensionCreatePanel, {
      props: {
        saving: false,
        error: 'Switch is temporarily unavailable.',
        fieldErrors: {},
        options: defaultExtensionFormOptions(),
      },
      global: {
        components: { FormSelect, ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
        },
      },
    })

    expect(wrapper.text()).toContain('Switch is temporarily unavailable.')
  })

  it('opens the shared Device-domain editor as a subview of the existing slide-over', async () => {
    const wrapper = mount(ExtensionCreatePanel, {
      props: {
        saving: false,
        error: null,
        fieldErrors: {},
        options: defaultExtensionFormOptions(),
      },
      global: {
        components: { FormSelect, ToggleSwitch },
        stubs: {
          CrudSlideOver: {
            props: ['title'],
            template: '<div><h1>{{ title }}</h1><slot /></div>',
          },
          DeviceDraftForm: { template: '<div data-testid="device-draft" />' },
        },
      },
    })
    const createToggles = wrapper
      .findAllComponents(ToggleSwitch)
      .filter((toggle) => toggle.props('label') === 'Create')

    expect(wrapper.get('[data-testid="device-subview"]').attributes('style')).toContain(
      'display: none',
    )
    createToggles.at(-1)!.vm.$emit('update:modelValue', true)
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('h1').text()).toBe('Configure device')
    expect(wrapper.get('[data-testid="device-subview"]').attributes('style')).not.toContain(
      'display: none',
    )
  })
})
