import { describe, expect, it } from 'vitest'
import { defaultDeviceConfiguration, legacyDeviceSchemaCompatibility } from '../deviceForm'
import type {
  DeviceInput,
  DeviceProvisioningCatalog,
  DeviceSchemaCompatibility,
  FullDeviceSipInput,
} from '../types/device'
import { createDeviceFormSchema, deviceFormSchema } from './deviceFormSchema'

type ValidDevice = DeviceInput & {
  provision: NonNullable<DeviceInput['provision']>
  sip: FullDeviceSipInput
  media: NonNullable<DeviceInput['media']>
  call_recording: NonNullable<DeviceInput['call_recording']>
}

function validDevice(): ValidDevice {
  const configuration = defaultDeviceConfiguration()

  return {
    name: 'Reception phone',
    device_type: 'sip_device' as const,
    provision: {
      endpoint_brand: 'Yealink',
      endpoint_family: 'T5',
      endpoint_model: 'T54W',
      check_sync_event: null,
      check_sync_reload: null,
      check_sync_reboot: null,
    },
    mac_address: '00:11:22:33:44:55',
    is_enabled: true,
    assigned_extension_id: null,
    sip: {
      method: configuration.sip.method,
      username: 'reception',
      password: 'a-long-random-secret',
      realm: configuration.sip.realm,
      expire_seconds: configuration.sip.expire_seconds,
      invite_format: configuration.sip.invite_format,
      ip: configuration.sip.ip,
      number: configuration.sip.number,
      route: configuration.sip.route,
      static_route: configuration.sip.static_route,
      custom_sip_interface: configuration.sip.custom_sip_interface,
      forward: configuration.sip.forward,
      proxy: configuration.sip.proxy,
      static_invite: configuration.sip.static_invite,
      transport: configuration.sip.transport,
      ignore_completed_elsewhere: configuration.sip.ignore_completed_elsewhere,
      custom_sip_headers: configuration.sip.custom_sip_headers,
    },
    media: configuration.media,
    call_recording: configuration.call_recording,
  }
}

describe('deviceFormSchema', () => {
  it('accepts a supported device payload', () => {
    expect(deviceFormSchema.safeParse(validDevice()).success).toBe(true)
  })

  it('rejects a model outside the selected provisioner catalog branch', () => {
    const catalog: DeviceProvisioningCatalog = {
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
    }
    const input = validDevice()
    input.provision.endpoint_model = 'T99 Unknown'

    const result = createDeviceFormSchema(legacyDeviceSchemaCompatibility, catalog).safeParse(input)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toContain(
      'provision.endpoint_model',
    )
  })

  it('rejects invalid device, credential, and recording values with dotted paths', () => {
    const input = validDevice()
    input.name = ''
    input.mac_address = 'not-a-mac'
    input.sip.password = 'short'
    input.call_recording.inbound.offnet.time_limit = 20_000

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'name',
        'mac_address',
        'sip.password',
        'call_recording.inbound.offnet.time_limit',
      ]),
    )
  })

  it('matches the Switch device-name limit', () => {
    const input = validDevice()
    input.name = 'D'.repeat(129)

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return
    expect(result.error.issues.some((issue) => issue.path.join('.') === 'name')).toBe(true)
  })

  it('requires an IP for IP-authenticated SIP devices', () => {
    const input = validDevice()
    input.sip.method = 'ip'

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return
    expect(result.error.issues.some((issue) => issue.path.join('.') === 'sip.ip')).toBe(true)
  })

  it('requires a destination URI for SIP URI devices', () => {
    const input: DeviceInput = {
      ...validDevice(),
      device_type: 'sip_uri',
      sip: { invite_format: 'route', route: null },
    }

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return
    expect(result.error.issues.some((issue) => issue.path.join('.') === 'sip.route')).toBe(true)
  })

  it.each(['cellphone', 'landline'] as const)(
    'accepts the minimal %s workflow and rejects endpoint fields',
    (deviceType) => {
      const configuration = defaultDeviceConfiguration()
      const input: DeviceInput = {
        name: `Test ${deviceType}`,
        device_type: deviceType,
        is_enabled: true,
        assigned_extension_id: null,
        call_forward: {
          ...configuration.call_forward,
          enabled: true,
          number: '+15551234567',
        },
        contact_list: { exclude: false },
      }

      expect(deviceFormSchema.safeParse(input).success).toBe(true)

      const withEndpointFields: DeviceInput = {
        ...input,
        sip: {
          invite_format: 'route',
          route: 'sip:must-not-submit@example.com',
        },
        media: configuration.media,
      }
      expect(deviceFormSchema.safeParse(withEndpointFields).success).toBe(false)

      const divergent = {
        ...input,
        is_enabled: false,
      }
      const result = deviceFormSchema.safeParse(divergent)
      expect(result.success).toBe(false)
      if (result.success) return
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toContain(
        'call_forward.enabled',
      )
    },
  )

  it('validates JSON-backed routing fields and rejects duplicate header names', () => {
    const input = {
      ...validDevice(),
      music_on_hold: { media_id: '2ec6914e-91aa-4b09-bbe7-7bf81631ebf7' },
      outbound_flags: { static: ['fax'], dynamic: ['regional'] },
      dial_plan: {
        system: ['north_america'],
        rules: [
          { pattern: '^([2-9][0-9]{6})$', description: 'Local', prefix: '+1555', suffix: null },
        ],
      },
      metaflows: { binding_digit: '*', digit_timeout: 2000, listen_on: 'both' as const },
    }
    input.sip.custom_sip_headers!.out = [
      { name: 'X-Device', value: 'one' },
      { name: 'x-device', value: 'two' },
    ]

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return
    expect(
      result.error.issues.some(
        (issue) => issue.path.join('.') === 'sip.custom_sip_headers.out.1.name',
      ),
    ).toBe(true)
  })

  it('accepts general flags, formatters, and provisioning event fields', () => {
    const input = {
      ...validDevice(),
      provision: { ...validDevice().provision, check_sync_event: 'check-sync' },
      flags: ['crm_managed'],
      formatters: [
        {
          field: 'request',
          direction: 'outbound' as const,
          match_invite_format: false,
          prefix: '+1',
          regex: '^([0-9]+)$',
          strip: false,
          suffix: null,
          value: null,
        },
      ],
    }
    expect(deviceFormSchema.safeParse(input).success).toBe(true)
  })

  it('uses connected schema limits and rejects unsupported compatibility fields', () => {
    const input = validDevice()
    input.call_forward = {
      ...defaultDeviceConfiguration().call_forward,
      number: '+155512345678901',
    }
    input.sip.invite_format = 'strip_plus'
    input.sip.proxy = 'proxy.example.test'
    input.provision.id = 'template-t54w'

    const result = createDeviceFormSchema(legacyDeviceSchemaCompatibility).safeParse(input)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'call_forward.number',
        'sip.invite_format',
        'sip.proxy',
        'provision.id',
      ]),
    )
  })

  it('accepts current schema SIP fields and array endpoint models', () => {
    const compatibility: DeviceSchemaCompatibility = {
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
      provision: {
        template_id: true,
        endpoint_model_types: ['string', 'array'],
        check_sync_event: false,
        check_sync_reload: false,
        check_sync_reboot: false,
      },
    }
    const input = validDevice()
    input.provision = {
      id: 'template-t54w',
      endpoint_brand: 'yealink',
      endpoint_family: 't5',
      endpoint_model: ['t54w', 't54w-v2'],
    }
    input.sip = {
      ...input.sip,
      invite_format: 'strip_plus',
      custom_sip_interface: 'internal',
      forward: '192.0.2.10',
      proxy: 'proxy.example.test',
      static_invite: 'reception',
      transport: 'tcp',
    }

    expect(createDeviceFormSchema(compatibility).safeParse(input).success).toBe(true)
  })

  it('accepts omitted SIP fields that the connected schema does not expose', () => {
    const input = validDevice()

    delete input.sip.custom_sip_interface
    delete input.sip.forward
    delete input.sip.proxy
    delete input.sip.static_invite
    delete input.sip.transport

    expect(createDeviceFormSchema(legacyDeviceSchemaCompatibility).safeParse(input).success).toBe(
      true,
    )
  })

  it('accepts guided metaflow actions and rejects unsafe module fields', () => {
    const valid = deviceFormSchema.safeParse({
      ...validDevice(),
      metaflows: {
        binding_digit: '*',
        digit_timeout: 2000,
        listen_on: 'both',
        actions: [
          {
            trigger_type: 'number',
            trigger: '1',
            module: 'transfer',
            data: { target: '1001', transfer_type: 'blind' },
          },
        ],
      },
    })
    const unsafe = deviceFormSchema.safeParse({
      ...validDevice(),
      metaflows: {
        binding_digit: '*',
        digit_timeout: 2000,
        listen_on: 'both',
        actions: [
          {
            trigger_type: 'number',
            trigger: '*1',
            module: 'transfer',
            data: { private_id: 'upstream-id' },
          },
        ],
      },
    })

    expect(valid.success).toBe(true)
    expect(unsafe.success).toBe(false)
  })

  it('accepts recursive resource-linked metaflows and validates public resource ids', () => {
    const valid = deviceFormSchema.safeParse({
      ...validDevice(),
      metaflows: {
        binding_digit: '*',
        digit_timeout: 2000,
        listen_on: 'both',
        actions: [
          {
            trigger_type: 'number',
            trigger: '1',
            module: 'play',
            data: { media_id: '2ec6914e-91aa-4b09-bbe7-7bf81631ebf7', leg: 'both' },
            children: [
              {
                key: 'success',
                module: 'callflow',
                data: { callflow_id: '66216cb4-ae32-4096-87e9-c4644591aeb2' },
                children: [],
              },
            ],
          },
        ],
      },
    })
    const invalid = deviceFormSchema.safeParse({
      ...validDevice(),
      metaflows: {
        actions: [
          {
            trigger_type: 'number',
            trigger: '1',
            module: 'play',
            data: { media_id: 'raw-switch-resource-id' },
            children: [],
          },
        ],
      },
    })

    expect(valid.success).toBe(true)
    expect(invalid.success).toBe(false)
  })

  it('rejects invalid formatter field names and directions', () => {
    const result = deviceFormSchema.safeParse({
      ...validDevice(),
      formatters: [
        {
          field: 'invalid field',
          direction: 'sideways',
          match_invite_format: false,
          prefix: null,
          regex: null,
          strip: false,
          suffix: null,
          value: null,
        },
      ],
    })

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['formatters.0.field', 'formatters.0.direction']),
    )
  })
})
