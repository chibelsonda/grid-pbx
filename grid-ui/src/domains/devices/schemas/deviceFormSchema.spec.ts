import { describe, expect, it } from 'vitest'
import { defaultDeviceConfiguration } from '../deviceForm'
import { deviceFormSchema } from './deviceFormSchema'

function validDevice() {
  const configuration = defaultDeviceConfiguration()

  return {
    name: 'Reception phone',
    device_type: 'sip_device' as const,
    provision: {
      endpoint_brand: 'Yealink',
      endpoint_family: null,
      endpoint_model: 'T54W',
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

  it('requires an IP for IP-authenticated SIP devices', () => {
    const input = validDevice()
    input.sip.method = 'ip'

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return
    expect(result.error.issues.some((issue) => issue.path.join('.') === 'sip.ip')).toBe(true)
  })

  it('requires a destination URI for SIP URI devices', () => {
    const input = { ...validDevice(), device_type: 'sip_uri' as const }
    input.sip.invite_format = 'route'
    input.sip.route = null

    const result = deviceFormSchema.safeParse(input)
    expect(result.success).toBe(false)

    if (result.success) return
    expect(result.error.issues.some((issue) => issue.path.join('.') === 'sip.route')).toBe(true)
  })

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
    input.sip.custom_sip_headers.out = [
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
})
