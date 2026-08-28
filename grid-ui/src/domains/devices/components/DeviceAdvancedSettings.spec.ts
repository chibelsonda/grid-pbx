import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { defaultDeviceConfiguration } from '../deviceForm'
import type { DeviceType } from '../types/device'
import DeviceAdvancedSettings from './DeviceAdvancedSettings.vue'

describe('DeviceAdvancedSettings', () => {
  it.each<[DeviceType, string[]]>([
    ['sip_device', ['Basic', 'Caller ID', 'SIP', 'Audio', 'Video', 'Options', 'Restrictions']],
    ['cellphone', ['Basic', 'Options']],
    ['smartphone', ['Basic', 'Wi-Fi calling', 'Options', 'Restrictions']],
    ['softphone', ['Basic', 'Caller ID', 'SIP', 'Audio', 'Video', 'Options', 'Restrictions']],
    ['landline', ['Basic', 'Options']],
    ['fax', ['Basic', 'Caller ID', 'SIP', 'Options', 'Restrictions']],
    ['ata', ['Basic', 'Caller ID', 'SIP', 'Options', 'Restrictions']],
    ['sip_uri', ['Basic', 'Options']],
  ])('shows the audited tabs for %s', (deviceType, expectedTabs) => {
    const wrapper = mount(DeviceAdvancedSettings, {
      props: {
        modelValue: defaultDeviceConfiguration(),
        deviceType,
        fieldErrors: {},
        firstErrorField: null,
        isEditing: false,
        restrictionOptions: [],
        mediaOptions: [],
      },
      slots: { basic: '<div>Basic phone settings</div>' },
      global: { components: { ToggleSwitch } },
    })

    const tabs = wrapper.findAll('[role="tab"]').map((tab) => tab.text())

    expect(tabs).toEqual(expectedTabs)
  })

  it('renders one visible Headless UI restriction select for every Switch classifier', async () => {
    const wrapper = mount(DeviceAdvancedSettings, {
      props: {
        modelValue: defaultDeviceConfiguration(),
        deviceType: 'sip_device',
        fieldErrors: {},
        firstErrorField: null,
        isEditing: false,
        restrictionOptions: [
          { key: 'tollfree_us', label: 'US TollFree', emergency: false },
          { key: 'toll_us', label: 'US Toll', emergency: false },
          { key: 'emergency', label: 'Emergency Dispatcher', emergency: true },
          { key: 'caribbean', label: 'Caribbean', emergency: false },
          { key: 'did_us', label: 'US DID', emergency: false },
          { key: 'international', label: 'International', emergency: false },
          { key: 'unknown', label: 'Unknown', emergency: false },
        ],
        mediaOptions: [],
      },
      global: {
        components: { ToggleSwitch },
      },
      attachTo: document.body,
    })

    const restrictionsTab = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Restrictions'))

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual([
      'Basic',
      'Caller ID',
      'SIP',
      'Audio',
      'Video',
      'Options',
      'Restrictions',
    ])

    expect(restrictionsTab).toBeDefined()
    await restrictionsTab?.trigger('click')

    const selects = wrapper.findAll('button[aria-label^="Restriction for"]')

    expect(selects).toHaveLength(7)
    expect(selects[0]?.text()).toContain('Inherit account policy')
    expect(wrapper.text()).toContain('Emergency Dispatcher')
    wrapper.unmount()
  })
})
