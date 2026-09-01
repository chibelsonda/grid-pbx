import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormSelect from '@/shared/components/FormSelect.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import ExtensionCreatePanel from './ExtensionCreatePanel.vue'
import ExtensionAdvancedCallingSettings from './ExtensionAdvancedCallingSettings.vue'
import ExtensionAdvancedTabSelector from './ExtensionAdvancedTabSelector.vue'
import ExtensionUserOptions from './ExtensionUserOptions.vue'
import DeviceDraftForm from '@/domains/devices/components/DeviceDraftForm.vue'
import { defaultExtensionFormOptions } from '../extensionForm'

describe('ExtensionCreatePanel', () => {
  it('uses the extra-wide panel required by its advanced tabs', () => {
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
            name: 'CrudSlideOver',
            props: ['width'],
            template: '<div><slot /></div>',
          },
        },
      },
    })

    expect(wrapper.findComponent({ name: 'CrudSlideOver' }).props('width')).toBe('extra-wide')
  })

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

  it('shows the verified advanced User controls under the shared Advanced tab', async () => {
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

    expect(wrapper.get('[data-testid="extension-advanced-section"]').attributes('style')).toContain(
      'display: none',
    )
    const tabs = wrapper.get('[aria-label="Extension form sections"]')
    await tabs.findAll('[role="tab"]')[1]!.trigger('click')
    await wrapper.vm.$nextTick()

    expect(
      wrapper.get('[data-testid="extension-advanced-section"]').attributes('style') ?? '',
    ).not.toContain('display: none')
    expect(wrapper.findComponent(ExtensionUserOptions).exists()).toBe(true)
    expect(wrapper.findComponent(ExtensionAdvancedCallingSettings).exists()).toBe(true)
    expect(wrapper.findComponent(ExtensionAdvancedTabSelector).exists()).toBe(true)
    expect(
      wrapper
        .get('[aria-label="Extension advanced sections"]')
        .findAll('[role="tab"]')
        .map((tab) => tab.text()),
    ).toEqual([
      'Caller ID',
      'Options',
      'Call Forward',
      'Password Management',
      'Hot Desking',
      'Restrictions',
      'Recording',
      'Media',
      'Routing & Profile',
      'Metaflows',
    ])
    expect(wrapper.get('[data-testid="extension-advanced-caller-id"]').text()).toContain(
      'Presence ID',
    )
    expect(wrapper.get('[data-testid="extension-advanced-options"]').text()).not.toContain(
      'Presence ID',
    )
    expect(wrapper.get('[data-testid="extension-advanced-options"]').text()).toContain(
      'Music on hold',
    )
    expect(wrapper.get('[data-testid="extension-advanced-media"]').text()).not.toContain(
      'Music on hold',
    )
    expect(
      wrapper.get('[data-testid="extension-advanced-options"]').attributes('style') ?? '',
    ).not.toContain('display: none')
    expect(
      wrapper.get('[data-testid="extension-advanced-call-forward"]').attributes('style'),
    ).toContain('display: none')
  })

  it.each([
    ['presence_id', 'extension-advanced-caller-id'],
    ['music_on_hold.media_id', 'extension-advanced-options'],
    ['call_forward.number', 'extension-advanced-call-forward'],
    ['password', 'extension-advanced-password'],
    ['hotdesk.id', 'extension-advanced-hot-desking'],
    ['call_restriction.international.action', 'extension-advanced-restrictions'],
    ['media.audio.codecs', 'extension-advanced-media'],
    ['profile.title', 'extension-advanced-routing-profile'],
    ['metaflows.binding_digit', 'extension-advanced-metaflows'],
  ])('opens the matching Advanced tab for the server field %s', (field, testId) => {
    const wrapper = mount(ExtensionCreatePanel, {
      props: {
        saving: false,
        error: null,
        fieldErrors: { [field]: ['Invalid value.'] },
        options: defaultExtensionFormOptions(),
      },
      global: {
        components: { FormSelect, ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
        },
      },
    })

    expect(
      wrapper.get('[data-testid="extension-advanced-section"]').attributes('style') ?? '',
    ).not.toContain('display: none')
    expect(wrapper.get(`[data-testid="${testId}"]`).attributes('style') ?? '').not.toContain(
      'display: none',
    )
    if (testId !== 'extension-advanced-options') {
      expect(
        wrapper.get('[data-testid="extension-advanced-options"]').attributes('style'),
      ).toContain('display: none')
    }
  })

  it('keeps nested call-forward switches reactive inside the focused tab', async () => {
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

    const formSections = wrapper.get('[aria-label="Extension form sections"]')
    await formSections.findAll('[role="tab"]')[1]!.trigger('click')
    const advancedSections = wrapper.get('[aria-label="Extension advanced sections"]')
    const callForwardTab = advancedSections
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes('Call Forward'))
    await callForwardTab!.trigger('click')
    const behavior = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Forwarding behavior'))
    await behavior!.trigger('click')
    const requireKeypress = wrapper
      .findAllComponents(ToggleSwitch)
      .find((toggle) => toggle.props('label') === 'Require keypress')

    expect(requireKeypress).toBeDefined()
    expect(requireKeypress!.props('modelValue')).toBe(true)
    await requireKeypress!.get('button[role="switch"]').trigger('click')
    expect(requireKeypress!.props('modelValue')).toBe(false)
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
