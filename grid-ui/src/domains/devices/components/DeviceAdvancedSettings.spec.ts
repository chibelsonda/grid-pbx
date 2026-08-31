import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { defaultDeviceConfiguration, legacyDeviceSchemaCompatibility } from '../deviceForm'
import type { DeviceSchemaCompatibility, DeviceType } from '../types/device'
import DeviceAdvancedSettings from './DeviceAdvancedSettings.vue'

describe('DeviceAdvancedSettings', () => {
  it.each<[DeviceType, string[]]>([
    ['sip_device', ['Basic', 'Caller ID', 'SIP', 'Audio', 'Video', 'Options', 'Restrictions']],
    ['cellphone', ['Basic', 'Options']],
    [
      'smartphone',
      ['Basic', 'Caller ID', 'Wi-Fi calling', 'Audio', 'Video', 'Options', 'Restrictions'],
    ],
    ['softphone', ['Basic', 'Caller ID', 'SIP', 'Audio', 'Video', 'Options', 'Restrictions']],
    ['landline', ['Basic', 'Options']],
    ['fax', ['Basic', 'Caller ID', 'SIP', 'Audio', 'Options', 'Restrictions']],
    ['ata', ['Basic', 'Caller ID', 'SIP', 'Audio', 'Options', 'Restrictions']],
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
        schemaCompatibility: legacyDeviceSchemaCompatibility,
      },
      slots: { basic: '<div>Basic phone settings</div>' },
      global: { components: { ToggleSwitch } },
    })

    const tabs = wrapper.findAll('[role="tab"]').map((tab) => tab.text())

    expect(tabs).toEqual(expectedTabs)
  })

  it.each<[DeviceType, string[], boolean, boolean]>([
    [
      'sip_device',
      [
        'Call waiting',
        'Do not disturb',
        'Exclude from queues',
        'Enable T.38 fax',
        'Hide from contact list',
        'Ignore completed elsewhere',
      ],
      true,
      true,
    ],
    [
      'cellphone',
      ['Require keypress', 'Keep original caller ID', 'Hide from contact list'],
      false,
      false,
    ],
    [
      'smartphone',
      [
        'Require keypress',
        'Keep original caller ID',
        'Call waiting',
        'Do not disturb',
        'Exclude from queues',
        'Hide from contact list',
      ],
      true,
      false,
    ],
    [
      'softphone',
      [
        'Call waiting',
        'Do not disturb',
        'Exclude from queues',
        'Enable T.38 fax',
        'Hide from contact list',
        'Ignore completed elsewhere',
      ],
      true,
      true,
    ],
    [
      'landline',
      ['Require keypress', 'Keep original caller ID', 'Hide from contact list'],
      false,
      false,
    ],
    [
      'fax',
      [
        'Call waiting',
        'Do not disturb',
        'Exclude from queues',
        'Enable T.38 fax',
        'Hide from contact list',
      ],
      true,
      false,
    ],
    [
      'ata',
      [
        'Call waiting',
        'Do not disturb',
        'Exclude from queues',
        'Enable T.38 fax',
        'Hide from contact list',
      ],
      true,
      false,
    ],
    ['sip_uri', ['Hide from contact list'], false, false],
  ])(
    'shows the schema-backed Options capabilities for %s',
    async (deviceType, expectedLabels, advancedRouting, recording) => {
      const wrapper = mount(DeviceAdvancedSettings, {
        props: {
          modelValue: defaultDeviceConfiguration(),
          deviceType,
          fieldErrors: {},
          firstErrorField: null,
          isEditing: false,
          restrictionOptions: [],
          mediaOptions: [],
          schemaCompatibility: legacyDeviceSchemaCompatibility,
        },
        slots: { basic: '<div>Basic phone settings</div>' },
        global: { components: { ToggleSwitch } },
      })

      await wrapper
        .findAll('[role="tab"]')
        .find((tab) => tab.text() === 'Options')
        ?.trigger('click')

      expect(
        wrapper.findAllComponents(ToggleSwitch).map((toggle) => toggle.props('label')),
      ).toEqual(expect.arrayContaining(expectedLabels))
      expect(wrapper.text().includes('Call recording')).toBe(recording)
      expect(wrapper.text().includes('Routing and endpoint behavior')).toBe(advancedRouting)
      expect(wrapper.text().includes('Ringtone headers')).toBe(deviceType === 'sip_device')
    },
  )

  it.each(['cellphone', 'landline', 'smartphone'] as const)(
    'shows current-schema forwarding fields for %s',
    async (deviceType) => {
      const wrapper = mount(DeviceAdvancedSettings, {
        props: {
          modelValue: defaultDeviceConfiguration(),
          deviceType,
          fieldErrors: {},
          firstErrorField: null,
          isEditing: false,
          restrictionOptions: [],
          mediaOptions: [],
          schemaCompatibility: legacyDeviceSchemaCompatibility,
        },
        global: { components: { ToggleSwitch } },
      })

      await wrapper
        .findAll('[role="tab"]')
        .find((tab) => tab.text() === 'Options')
        ?.trigger('click')
      await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Advanced forwarding')
        ?.trigger('click')

      expect(
        wrapper.findAllComponents(ToggleSwitch).map((toggle) => toggle.props('label')),
      ).toEqual(
        expect.arrayContaining([
          'Direct calls only',
          'Forward only when offline',
          'Ignore early media',
          'Replace this device',
        ]),
      )
    },
  )

  it('matches the minimal Kazoo SIP URI Options workflow', async () => {
    const wrapper = mount(DeviceAdvancedSettings, {
      props: {
        modelValue: defaultDeviceConfiguration(),
        deviceType: 'sip_uri',
        fieldErrors: {},
        firstErrorField: null,
        isEditing: false,
        restrictionOptions: [],
        mediaOptions: [],
        schemaCompatibility: legacyDeviceSchemaCompatibility,
      },
      slots: { basic: '<div>Basic phone settings</div>' },
      global: { components: { ToggleSwitch } },
    })

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text() === 'Options')
      ?.trigger('click')

    expect(wrapper.findAllComponents(ToggleSwitch).map((toggle) => toggle.props('label'))).toEqual([
      'Hide from contact list',
    ])
    expect(wrapper.text()).not.toContain('Presence ID')
    expect(wrapper.text()).not.toContain('Custom SIP headers')
    expect(wrapper.text()).not.toContain('Dial plan')
    expect(wrapper.text()).not.toContain('Metaflows and hotdesk')
  })

  it.each<[DeviceType, boolean, boolean]>([
    ['sip_device', true, true],
    ['smartphone', false, false],
    ['softphone', true, true],
    ['fax', true, false],
    ['ata', true, false],
  ])(
    'shows the audited T.38 and completed-elsewhere controls for %s',
    async (deviceType, faxOption, completedElsewhere) => {
      const wrapper = mount(DeviceAdvancedSettings, {
        props: {
          modelValue: defaultDeviceConfiguration(),
          deviceType,
          fieldErrors: {},
          firstErrorField: null,
          isEditing: false,
          restrictionOptions: [],
          mediaOptions: [],
          schemaCompatibility: legacyDeviceSchemaCompatibility,
        },
        slots: { basic: '<div>Basic phone settings</div>' },
        global: { components: { ToggleSwitch } },
      })

      await wrapper
        .findAll('[role="tab"]')
        .find((tab) => tab.text() === 'Options')
        ?.trigger('click')
      expect(wrapper.text().includes('Enable T.38 fax')).toBe(faxOption)
      expect(wrapper.text().includes('Ignore completed elsewhere')).toBe(completedElsewhere)

      await wrapper
        .findAll('[role="tab"]')
        .find((tab) => tab.text() === 'SIP' || tab.text() === 'Wi-Fi calling')
        ?.trigger('click')
      expect(wrapper.text()).not.toContain('Ignore completed elsewhere')
    },
  )

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
        schemaCompatibility: legacyDeviceSchemaCompatibility,
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

  it('shows ordered codec and ringtone controls in their Switch-backed tabs', async () => {
    const wrapper = mount(DeviceAdvancedSettings, {
      props: {
        modelValue: defaultDeviceConfiguration(),
        deviceType: 'sip_device',
        fieldErrors: {},
        firstErrorField: null,
        isEditing: false,
        restrictionOptions: [],
        mediaOptions: [],
        schemaCompatibility: legacyDeviceSchemaCompatibility,
      },
      global: { components: { ToggleSwitch } },
      attachTo: document.body,
    })

    const audioTab = wrapper.findAll('[role="tab"]').find((tab) => tab.text() === 'Audio')
    await audioTab?.trigger('click')

    expect(wrapper.text()).toContain('Audio codec priority')
    expect(wrapper.text()).toContain('Music on hold')
    expect(wrapper.find('button[aria-label="Select device music on hold"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Move PCMA up"]').exists()).toBe(true)

    const optionsTab = wrapper.findAll('[role="tab"]').find((tab) => tab.text() === 'Options')
    await optionsTab?.trigger('click')

    expect(wrapper.text()).toContain('Ringtone headers')
    expect(wrapper.find('input[placeholder="Internal-ring"]').exists()).toBe(true)
    expect(wrapper.find('input[placeholder="External-ring"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it.each<DeviceType>(['sip_device', 'smartphone', 'softphone', 'fax', 'ata'])(
    'shows outbound flags in schema-backed routing for %s',
    async (deviceType) => {
      const wrapper = mount(DeviceAdvancedSettings, {
        props: {
          modelValue: defaultDeviceConfiguration(),
          deviceType,
          fieldErrors: {},
          firstErrorField: null,
          isEditing: false,
          restrictionOptions: [],
          mediaOptions: [],
          schemaCompatibility: legacyDeviceSchemaCompatibility,
        },
        global: { components: { ToggleSwitch } },
      })

      await wrapper
        .findAll('[role="tab"]')
        .find((tab) => tab.text() === 'Options')
        ?.trigger('click')

      expect(wrapper.find('textarea[placeholder="fax, trusted"]').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('WebRTC')
    },
  )

  it('shows SIP fields and invite formats advertised by the connected schema', async () => {
    const currentSchema: DeviceSchemaCompatibility = {
      ...legacyDeviceSchemaCompatibility,
      source: 'connected_switch',
      call_forward: { number_max_length: 35 },
      sip: {
        invite_formats: ['username', 'strip_plus', 'contact'],
        custom_sip_interface: true,
        forward: true,
        proxy: true,
        static_invite: true,
        transport: true,
      },
    }
    const wrapper = mount(DeviceAdvancedSettings, {
      props: {
        modelValue: defaultDeviceConfiguration(),
        deviceType: 'sip_device',
        fieldErrors: {},
        firstErrorField: null,
        isEditing: false,
        restrictionOptions: [],
        mediaOptions: [],
        schemaCompatibility: currentSchema,
      },
      global: { components: { ToggleSwitch } },
      attachTo: document.body,
    })

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text() === 'SIP')
      ?.trigger('click')

    expect(wrapper.text()).toContain('Custom SIP interface')
    expect(wrapper.text()).toContain('Forward IP')
    expect(wrapper.text()).toContain('SIP proxy')
    expect(wrapper.text()).toContain('Static SIP To user')
    expect(wrapper.text()).toContain('SIP transport')

    const inviteFormat = wrapper.findAll('button').find((button) => button.text() === 'Contact')
    await inviteFormat?.trigger('click')
    expect(document.body.textContent).toContain('Strip leading +')
    wrapper.unmount()
  })
})
