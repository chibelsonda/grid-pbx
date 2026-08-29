import { describe, expect, it } from 'vitest'
import {
  defaultDeviceConfiguration,
  deviceTypes,
  legacyDeviceSchemaCompatibility,
  supportsDeviceNotifications,
  supportsDeviceOption,
  supportsIgnoreCompletedElsewhere,
  supportsMusicOnHold,
  supportsOutboundFlags,
  supportsProvisioning,
  usesForwarding,
  usesSip,
} from './deviceForm'
import { buildDeviceInput, normalizeMacAddress } from './deviceInput'
import type { DeviceBasicForm, DeviceType } from './types/device'

function form(deviceType: DeviceType): DeviceBasicForm {
  return {
    name: `Test ${deviceType}`,
    device_type: deviceType,
    make: 'yealink',
    family: 't5x',
    model: 't54w',
    mac_address: '00:11:22:33:44:55',
    is_enabled: true,
    assigned_extension_id: '',
  }
}

describe('buildDeviceInput', () => {
  it('normalizes common provisioner MAC formats', () => {
    expect(normalizeMacAddress('0011.2233.4455')).toBe('00:11:22:33:44:55')
    expect(normalizeMacAddress('00-11-22-33-44-55')).toBe('00:11:22:33:44:55')
  })

  it.each(deviceTypes.map((type) => type.value))(
    'uses the field-capability matrix for %s',
    (deviceType) => {
      const configuration = defaultDeviceConfiguration()
      configuration.sip.route = 'sip:reception@example.com'
      const input = buildDeviceInput(
        form(deviceType),
        configuration,
        legacyDeviceSchemaCompatibility,
      )

      expect(input.contact_list).toEqual({ exclude: false })
      expect('call_forward' in input).toBe(usesForwarding(deviceType))
      expect('sip' in input).toBe(usesSip(deviceType))
      expect('provision' in input).toBe(supportsProvisioning(deviceType))
      expect('mac_address' in input).toBe(supportsProvisioning(deviceType))
      expect(input).not.toHaveProperty('call_waiting')
      expect('music_on_hold' in input).toBe(supportsMusicOnHold(deviceType))
      expect('outbound_flags' in input).toBe(
        supportsOutboundFlags(deviceType) || deviceType === 'fax',
      )
      expect('ringtones' in input).toBe(supportsDeviceOption(deviceType, 'ringtones'))
      expect('suppress_unregister_notifications' in input).toBe(
        supportsDeviceNotifications(deviceType),
      )
      if (input.sip && 'method' in input.sip) {
        expect('ignore_completed_elsewhere' in input.sip).toBe(
          supportsIgnoreCompletedElsewhere(deviceType),
        )
      }
    },
  )

  it('builds the minimal Kazoo-compatible SIP URI payload', () => {
    const configuration = defaultDeviceConfiguration()
    configuration.sip.route = ' sip:support@example.com '
    configuration.sip.invite_format = 'route'
    configuration.call_waiting.enabled = false
    configuration.music_on_hold.media_id = 'c438e735-b6b7-4f5e-b4c6-c009453b85b6'
    configuration.flags = ['must-not-submit']
    configuration.contact_list.exclude = true

    const input = buildDeviceInput(form('sip_uri'), configuration, legacyDeviceSchemaCompatibility)

    expect(input.sip).toEqual({ invite_format: 'route', route: 'sip:support@example.com' })
    expect(input.contact_list).toEqual({ exclude: true })
    expect(input).not.toHaveProperty('call_waiting')
    expect(input).not.toHaveProperty('do_not_disturb')
    expect(input).not.toHaveProperty('exclude_from_queues')
    expect(input).not.toHaveProperty('presence_id')
    expect(input).not.toHaveProperty('music_on_hold')
    expect(input).not.toHaveProperty('outbound_flags')
    expect(input).not.toHaveProperty('dial_plan')
    expect(input).not.toHaveProperty('metaflows')
    expect(input).not.toHaveProperty('flags')
    expect(input).not.toHaveProperty('formatters')
  })

  it.each(['cellphone', 'landline'] as const)(
    'builds a forwarding-only payload and synchronizes enabled for %s',
    (deviceType) => {
      const configuration = defaultDeviceConfiguration()
      configuration.call_forward.enabled = true
      configuration.call_forward.number = '+15551234567'
      configuration.music_on_hold.media_id = 'c438e735-b6b7-4f5e-b4c6-c009453b85b6'
      configuration.flags = ['must-not-submit']
      const deviceForm = { ...form(deviceType), is_enabled: false }

      const input = buildDeviceInput(deviceForm, configuration, legacyDeviceSchemaCompatibility)

      expect(input.is_enabled).toBe(false)
      expect(input.call_forward).toEqual({
        enabled: false,
        number: '+15551234567',
        keep_caller_id: true,
        require_keypress: true,
      })
      expect(input.contact_list).toEqual({ exclude: false })
      expect(input).not.toHaveProperty('sip')
      expect(input).not.toHaveProperty('media')
      expect(input).not.toHaveProperty('caller_id')
      expect(input).not.toHaveProperty('music_on_hold')
      expect(input).not.toHaveProperty('flags')
    },
  )

  it('preserves the required fax outbound flag', () => {
    const configuration = defaultDeviceConfiguration()
    configuration.outbound_flags.static = ['regional', 'fax']

    const input = buildDeviceInput(form('fax'), configuration, legacyDeviceSchemaCompatibility)

    expect(input.outbound_flags?.static).toEqual(['fax', 'regional'])
  })

  it('omits schema-supported fields that the simplified Kazoo workflow does not own', () => {
    const configuration = defaultDeviceConfiguration()
    configuration.call_waiting.enabled = false
    configuration.do_not_disturb.enabled = true
    configuration.exclude_from_queues = true
    configuration.language = 'en-US'
    configuration.timezone = 'America/New_York'
    configuration.mwi_unsolicited_updates = false
    configuration.register_overwrite_notify = true
    configuration.call_recording.any.any.enabled = true
    configuration.music_on_hold.media_id = 'c438e735-b6b7-4f5e-b4c6-c009453b85b6'
    configuration.flags = ['must-not-submit']
    configuration.sip.custom_sip_headers.in = [{ name: 'X-Existing', value: 'preserve-me' }]
    configuration.provision.check_sync_event = 'check-sync'

    const input = buildDeviceInput(
      form('sip_device'),
      configuration,
      legacyDeviceSchemaCompatibility,
    )

    expect(input).not.toHaveProperty('call_waiting')
    expect(input).not.toHaveProperty('do_not_disturb')
    expect(input).not.toHaveProperty('exclude_from_queues')
    expect(input).not.toHaveProperty('language')
    expect(input).not.toHaveProperty('timezone')
    expect(input).not.toHaveProperty('mwi_unsolicited_updates')
    expect(input).not.toHaveProperty('register_overwrite_notify')
    expect(input).not.toHaveProperty('call_recording')
    expect(input.music_on_hold).toEqual({
      media_id: 'c438e735-b6b7-4f5e-b4c6-c009453b85b6',
    })
    expect(input).not.toHaveProperty('flags')
    expect(input.sip).not.toHaveProperty('custom_sip_headers')
    expect(input.provision).not.toHaveProperty('check_sync_event')
  })
})
