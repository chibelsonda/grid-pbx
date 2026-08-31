import { describe, expect, it } from 'vitest'
import {
  defaultDeviceConfiguration,
  deviceAdvancedTabForError,
  deviceFieldCapabilities,
  deviceFormTabs,
  deviceOptionCapabilities,
  deviceTypes,
  hydrateDeviceConfiguration,
  hydrateDeviceRestrictions,
  isBasicDeviceErrorField,
  supportsFaxOption,
  supportsIgnoreCompletedElsewhere,
  supportsMusicOnHold,
  supportsOutboundFlags,
  supportsProvisioning,
  supportsDeviceOption,
  supportsDeviceFieldGroup,
  supportsVideo,
  usesForwarding,
  usesSip,
} from './deviceForm'

describe('device form schema', () => {
  it('offers every addable upstream device type', () => {
    expect(deviceTypes.map((deviceType) => deviceType.value)).toEqual([
      'sip_device',
      'cellphone',
      'smartphone',
      'landline',
      'softphone',
      'fax',
      'ata',
      'sip_uri',
    ])
    expect(deviceTypes.every((deviceType) => deviceType.icon)).toBe(true)
  })

  it('selects conditional controls from the device type', () => {
    expect(usesSip('sip_device')).toBe(true)
    expect(usesSip('cellphone')).toBe(false)
    expect(usesForwarding('smartphone')).toBe(true)
    expect(usesForwarding('softphone')).toBe(false)
    expect(supportsVideo('softphone')).toBe(true)
    expect(supportsVideo('fax')).toBe(false)
    expect(supportsProvisioning('ata')).toBe(true)
    expect(supportsFaxOption('sip_device')).toBe(true)
    expect(supportsFaxOption('softphone')).toBe(true)
    expect(supportsFaxOption('smartphone')).toBe(false)
    expect(supportsIgnoreCompletedElsewhere('sip_device')).toBe(true)
    expect(supportsIgnoreCompletedElsewhere('fax')).toBe(false)
    expect(supportsOutboundFlags('smartphone')).toBe(true)
    expect(supportsOutboundFlags('fax')).toBe(false)
    expect(supportsMusicOnHold('softphone')).toBe(true)
    expect(supportsMusicOnHold('ata')).toBe(false)
  })

  it('uses a per-device tab matrix based on the Kazoo workflow and Switch capabilities', () => {
    expect(deviceFormTabs.cellphone).toEqual(['basic', 'options'])
    expect(deviceFormTabs.landline).toEqual(['basic', 'options'])
    expect(deviceFormTabs.sip_uri).toEqual(['basic', 'options'])
    expect(deviceFormTabs.smartphone).toEqual([
      'basic',
      'caller-id',
      'sip',
      'audio',
      'video',
      'options',
      'restrictions',
    ])
    expect(deviceFormTabs.fax).toEqual([
      'basic',
      'caller-id',
      'sip',
      'audio',
      'options',
      'restrictions',
    ])
    expect(deviceFormTabs.ata).toEqual([
      'basic',
      'caller-id',
      'sip',
      'audio',
      'options',
      'restrictions',
    ])
    expect(deviceFormTabs.sip_device).toEqual(
      expect.arrayContaining(['caller-id', 'sip', 'audio', 'video', 'options', 'restrictions']),
    )
  })

  it('uses a field-level capability matrix for all eight device types', () => {
    expect(Object.keys(deviceFieldCapabilities)).toEqual([
      'sip_device',
      'cellphone',
      'smartphone',
      'softphone',
      'landline',
      'fax',
      'ata',
      'sip_uri',
    ])

    for (const deviceType of deviceTypes.map((type) => type.value)) {
      expect(supportsDeviceFieldGroup(deviceType, 'contact-list')).toBe(true)
    }

    expect(supportsDeviceFieldGroup('sip_uri', 'endpoint-behavior')).toBe(false)
    expect(supportsDeviceFieldGroup('sip_uri', 'advanced-routing')).toBe(false)
    expect(supportsDeviceFieldGroup('sip_device', 'advanced-routing')).toBe(true)
  })

  it('uses the audited Kazoo Options matrix for all eight device types', () => {
    expect(Object.keys(deviceOptionCapabilities)).toEqual(deviceTypes.map((type) => type.value))
    expect([...deviceOptionCapabilities.sip_device]).toEqual([
      'ringtones',
      'fax',
      'contact-list',
      'ignore-completed-elsewhere',
    ])
    expect([...deviceOptionCapabilities.cellphone]).toEqual(['forwarding', 'contact-list'])
    expect([...deviceOptionCapabilities.smartphone]).toEqual(['forwarding', 'contact-list'])
    expect([...deviceOptionCapabilities.softphone]).toEqual([
      'fax',
      'contact-list',
      'ignore-completed-elsewhere',
    ])
    expect([...deviceOptionCapabilities.landline]).toEqual(['forwarding', 'contact-list'])
    expect([...deviceOptionCapabilities.fax]).toEqual(['fax', 'contact-list'])
    expect([...deviceOptionCapabilities.ata]).toEqual(['fax', 'contact-list'])
    expect([...deviceOptionCapabilities.sip_uri]).toEqual(['contact-list'])
    expect(supportsDeviceOption('sip_uri', 'fax')).toBe(false)
  })

  it('routes validation errors to their visible form tabs', () => {
    expect(isBasicDeviceErrorField('name')).toBe(true)
    expect(isBasicDeviceErrorField('provision.endpoint_model')).toBe(true)
    expect(isBasicDeviceErrorField('sip.password')).toBe(false)
    expect(deviceAdvancedTabForError('sip.password')).toBe('sip')
    expect(deviceAdvancedTabForError('sip.ignore_completed_elsewhere')).toBe('options')
    expect(deviceAdvancedTabForError('media.progress_timeout')).toBe('audio')
    expect(deviceAdvancedTabForError('media.video.codecs')).toBe('video')
    expect(deviceAdvancedTabForError('outbound_flags.static', 'sip_device')).toBe('sip')
    expect(deviceAdvancedTabForError('music_on_hold.media_id', 'softphone')).toBe('audio')
    expect(deviceAdvancedTabForError('call_recording.inbound.offnet.time_limit')).toBe('options')
    expect(deviceAdvancedTabForError('caller_id.external.number')).toBe('caller-id')
    expect(deviceAdvancedTabForError('call_restriction.international.action')).toBe('restrictions')
    expect(deviceAdvancedTabForError('timezone')).toBe('options')
    expect(deviceAdvancedTabForError('call_waiting.enabled')).toBe('options')
  })

  it('hydrates live classifiers with inherit defaults without replacing saved restrictions', () => {
    const restrictions = hydrateDeviceRestrictions({ international: { action: 'deny' } }, [
      { key: 'tollfree_us', label: 'US TollFree', emergency: false },
      { key: 'international', label: 'International', emergency: false },
    ])

    expect(restrictions).toEqual({
      closed_groups: { action: 'inherit' },
      tollfree_us: { action: 'inherit' },
      international: { action: 'deny' },
    })
  })

  it('hydrates nested switch settings while keeping credentials write-only', () => {
    const configuration = hydrateDeviceConfiguration({
      call_forward: {
        ...defaultDeviceConfiguration().call_forward,
        enabled: true,
        number: '+15551234567',
      },
      sip: {
        ...defaultDeviceConfiguration().sip,
        username: 'must-not-hydrate',
        password: 'must-not-hydrate',
        username_configured: true,
      },
      media: {
        ...defaultDeviceConfiguration().media,
        audio: { codecs: ['OPUS', 'PCMU'] },
      },
      call_recording: {
        ...defaultDeviceConfiguration().call_recording,
        inbound: {
          ...defaultDeviceConfiguration().call_recording.inbound,
          offnet: {
            ...defaultDeviceConfiguration().call_recording.inbound.offnet,
            enabled: true,
            format: 'wav',
            time_limit: 3600,
          },
        },
      },
    })

    expect(configuration.call_forward.number).toBe('+15551234567')
    expect(configuration.media.audio.codecs).toEqual(['OPUS', 'PCMU'])
    expect(configuration.sip.username_configured).toBe(true)
    expect(configuration.sip.username).toBeNull()
    expect(configuration.sip.password).toBeNull()
    expect(configuration.call_recording.inbound.offnet.enabled).toBe(true)
    expect(configuration.call_recording.inbound.offnet.format).toBe('wav')
    expect(configuration.call_recording.inbound.offnet.time_limit).toBe(3600)
  })
})
