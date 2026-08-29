import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { defaultDeviceConfiguration, legacyDeviceSchemaCompatibility } from '../deviceForm'
import type { DeviceBasicForm, DeviceSchemaCompatibility } from '../types/device'
import DeviceBasicSettings from './DeviceBasicSettings.vue'

const form = (): DeviceBasicForm => ({
  name: '',
  device_type: 'sip_device',
  make: '',
  family: '',
  model: '',
  mac_address: '',
  is_enabled: true,
  assigned_extension_id: '',
})

describe('DeviceBasicSettings', () => {
  it('marks a field with a validation error as invalid', () => {
    const wrapper = mount(DeviceBasicSettings, {
      props: {
        form: form(),
        configuration: defaultDeviceConfiguration(),
        extensionOptions: [],
        fieldErrors: { name: ['Enter a device name.'] },
        provisioningCatalog: { available: false, reason: null, brands: [] },
        schemaCompatibility: legacyDeviceSchemaCompatibility,
      },
      global: { components: { ToggleSwitch } },
    })

    expect(
      wrapper.get('input[placeholder="Reception Desk Phone"]').attributes('aria-invalid'),
    ).toBe('true')
  })

  it('shows the Kazoo-compatible notify-when-unregistered control for a SIP device', async () => {
    const configuration = defaultDeviceConfiguration()
    const wrapper = mount(DeviceBasicSettings, {
      props: {
        form: form(),
        configuration,
        extensionOptions: [],
        fieldErrors: {},
        provisioningCatalog: { available: false, reason: null, brands: [] },
        schemaCompatibility: legacyDeviceSchemaCompatibility,
      },
      global: { components: { ToggleSwitch } },
    })

    const toggle = wrapper
      .findAllComponents(ToggleSwitch)
      .find((component) => component.props('label') === 'Notify when unregistered')

    expect(toggle?.props('modelValue')).toBe(true)
    await toggle?.vm.$emit('update:modelValue', false)
    expect(configuration.suppress_unregister_notifications).toBe(true)
  })

  it('hides notify-when-unregistered for a device type without registration notifications', () => {
    const wrapper = mount(DeviceBasicSettings, {
      props: {
        form: { ...form(), device_type: 'landline' },
        configuration: defaultDeviceConfiguration(),
        extensionOptions: [],
        fieldErrors: {},
        provisioningCatalog: { available: false, reason: null, brands: [] },
        schemaCompatibility: legacyDeviceSchemaCompatibility,
      },
      global: { components: { ToggleSwitch } },
    })

    expect(wrapper.text()).not.toContain('Notify when unregistered')
  })

  it('shows current provisioning fields and applies the connected forward-number limit', () => {
    const compatibility: DeviceSchemaCompatibility = {
      ...legacyDeviceSchemaCompatibility,
      source: 'connected_switch',
      call_forward: { number_max_length: 35 },
      provision: {
        ...legacyDeviceSchemaCompatibility.provision,
        template_id: true,
        endpoint_model_types: ['string', 'array'],
      },
    }
    const wrapper = mount(DeviceBasicSettings, {
      props: {
        form: { ...form(), device_type: 'cellphone' },
        configuration: defaultDeviceConfiguration(),
        extensionOptions: [],
        fieldErrors: {},
        provisioningCatalog: { available: false, reason: null, brands: [] },
        schemaCompatibility: compatibility,
      },
      global: { components: { ToggleSwitch } },
    })

    expect(wrapper.find('input[placeholder="+15551234567"]').attributes('maxlength')).toBe('35')
  })

  it.each(['cellphone', 'landline'] as const)(
    'keeps %s enabled and call-forward enabled synchronized',
    async (deviceType) => {
      const deviceForm = { ...form(), device_type: deviceType }
      const configuration = defaultDeviceConfiguration()
      configuration.call_forward.enabled = true
      const wrapper = mount(DeviceBasicSettings, {
        props: {
          form: deviceForm,
          configuration,
          extensionOptions: [],
          fieldErrors: {},
          provisioningCatalog: { available: false, reason: null, brands: [] },
          schemaCompatibility: legacyDeviceSchemaCompatibility,
        },
        global: { components: { ToggleSwitch } },
      })

      const enabled = wrapper
        .findAllComponents(ToggleSwitch)
        .find((component) => component.props('label') === 'Enabled')

      await enabled?.vm.$emit('update:modelValue', false)

      expect(deviceForm.is_enabled).toBe(false)
      expect(configuration.call_forward.enabled).toBe(false)
    },
  )

  it('shows template and model-list controls for a provisionable current-schema device', () => {
    const compatibility: DeviceSchemaCompatibility = {
      ...legacyDeviceSchemaCompatibility,
      source: 'connected_switch',
      provision: {
        ...legacyDeviceSchemaCompatibility.provision,
        template_id: true,
        endpoint_model_types: ['string', 'array'],
      },
    }
    const wrapper = mount(DeviceBasicSettings, {
      props: {
        form: form(),
        configuration: defaultDeviceConfiguration(),
        extensionOptions: [],
        fieldErrors: {},
        provisioningCatalog: { available: false, reason: null, brands: [] },
        schemaCompatibility: compatibility,
      },
      global: { components: { ToggleSwitch } },
    })

    expect(wrapper.text()).toContain('Model identifiers')
    expect(wrapper.text()).toContain('Provisioner template ID')
  })

  it('maps catalog selector keys and the provider template id independently', async () => {
    const deviceForm = form()
    const configuration = defaultDeviceConfiguration()
    const wrapper = mount(DeviceBasicSettings, {
      props: {
        form: deviceForm,
        configuration,
        extensionOptions: [],
        fieldErrors: {},
        provisioningCatalog: {
          available: true,
          reason: null,
          brands: [
            {
              id: 'yealink',
              name: 'Yealink',
              families: [
                {
                  id: 't5',
                  name: 'T5',
                  models: [{ id: 't54w', name: 'T54W', template_id: 'yealink_t5_t54w' }],
                },
              ],
            },
          ],
        },
        schemaCompatibility: {
          ...legacyDeviceSchemaCompatibility,
          provision: { ...legacyDeviceSchemaCompatibility.provision, template_id: true },
        },
      },
      global: { components: { ToggleSwitch } },
    })

    const selectors = wrapper.findAllComponents(FormListbox)
    await selectors[0]?.vm.$emit('update:modelValue', 'yealink')
    await selectors[1]?.vm.$emit('update:modelValue', 't5')
    await selectors[2]?.vm.$emit('update:modelValue', 't54w')

    expect(deviceForm).toMatchObject({ make: 'yealink', family: 't5', model: 't54w' })
    expect(configuration.provision.endpoint_model).toBe('t54w')
    expect(configuration.provision.id).toBe('yealink_t5_t54w')
  })
})
