import type {
  DeviceConfiguration,
  DeviceFormTab,
  DeviceRecordingParameters,
  DeviceRecordingSource,
  DeviceRestrictionOption,
  DeviceOptions,
  DeviceSchemaCompatibility,
  DeviceType,
} from './types/device'
import type { Component } from 'vue'
import { endpointAudioCodecs, endpointVideoCodecs } from '@/shared/switch/endpointMedia'
import {
  ComputerDesktopIcon,
  CpuChipIcon,
  DevicePhoneMobileIcon,
  DeviceTabletIcon,
  GlobeAltIcon,
  HomeModernIcon,
  PhoneIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline'

export const deviceTypes: Array<{
  value: DeviceType
  label: string
  description: string
  icon: Component
}> = [
  {
    value: 'sip_device',
    label: 'VoIP phone',
    description: 'Desk phone or SIP endpoint',
    icon: DevicePhoneMobileIcon,
  },
  {
    value: 'cellphone',
    label: 'Cell phone',
    description: 'Forward calls to a mobile number',
    icon: PhoneIcon,
  },
  {
    value: 'smartphone',
    label: 'Smartphone',
    description: 'Mobile app or smart endpoint',
    icon: DeviceTabletIcon,
  },
  {
    value: 'landline',
    label: 'Landline',
    description: 'Forward calls to a fixed number',
    icon: HomeModernIcon,
  },
  {
    value: 'softphone',
    label: 'Softphone',
    description: 'Desktop or browser SIP client',
    icon: ComputerDesktopIcon,
  },
  { value: 'fax', label: 'Fax', description: 'SIP/T.38 fax endpoint', icon: PrinterIcon },
  {
    value: 'ata',
    label: 'ATA',
    description: 'Analog telephone adapter',
    icon: CpuChipIcon,
  },
  {
    value: 'sip_uri',
    label: 'SIP URI',
    description: 'Route directly to a SIP address',
    icon: GlobeAltIcon,
  },
]

export const audioCodecs = endpointAudioCodecs
export const videoCodecs = endpointVideoCodecs

export const legacyDeviceSchemaCompatibility: DeviceSchemaCompatibility = {
  source: 'bundled_legacy_fallback',
  schema_id: 'devices',
  call_forward: { number_max_length: 15 },
  sip: {
    invite_formats: ['username', 'npan', '1npan', 'e164', 'route', 'contact'],
    custom_sip_interface: false,
    forward: false,
    proxy: false,
    static_invite: false,
    transport: false,
  },
  provision: {
    template_id: false,
    endpoint_model_types: ['string', 'integer'],
    check_sync_event: true,
    check_sync_reload: true,
    check_sync_reboot: true,
  },
}

export function defaultDeviceOptions(): DeviceOptions {
  return {
    extensions: [],
    media: [],
    metaflow_resources: { callflows: [], devices: [] },
    caller_id_numbers: [],
    provisioning_catalog: {
      available: false,
      reason: 'Provisioning catalog has not been loaded.',
      brands: [],
    },
    device_schema: structuredClone(legacyDeviceSchemaCompatibility),
    restrictions: [],
  }
}

export function defaultDeviceConfiguration(): DeviceConfiguration {
  return {
    call_forward: {
      enabled: false,
      number: null,
      direct_calls_only: false,
      failover: false,
      ignore_early_media: true,
      keep_caller_id: true,
      require_keypress: true,
      substitute: true,
    },
    sip: {
      method: 'password',
      username: null,
      password: null,
      username_configured: false,
      realm: null,
      expire_seconds: 300,
      invite_format: 'contact',
      ip: null,
      number: null,
      route: null,
      static_route: null,
      custom_sip_interface: null,
      forward: null,
      proxy: null,
      static_invite: null,
      transport: null,
      ignore_completed_elsewhere: false,
      custom_sip_headers: { in: [], out: [] },
    },
    media: {
      audio: { codecs: ['PCMU', 'PCMA'] },
      video: { codecs: [] },
      bypass_media: false,
      encryption: { enforce_security: false, methods: [] },
      fax_option: false,
      ignore_early_media: false,
      progress_timeout: null,
    },
    caller_id: {
      internal: { name: null, number: null },
      external: { name: null, number: null },
      emergency: { name: null, number: null },
      asserted: { name: null, number: null, realm: null },
    },
    caller_id_options: { outbound_privacy: 'none' },
    call_waiting: { enabled: true },
    do_not_disturb: { enabled: false },
    contact_list: { exclude: false },
    exclude_from_queues: false,
    language: null,
    timezone: null,
    presence_id: null,
    mwi_unsolicited_updates: true,
    register_overwrite_notify: false,
    suppress_unregister_notifications: false,
    ringtones: { internal: null, external: null },
    call_restriction: { closed_groups: { action: 'inherit' } },
    call_recording: {
      any: defaultRecordingSource(),
      inbound: defaultRecordingSource(),
      outbound: defaultRecordingSource(),
    },
    music_on_hold: { media_id: null, media_name: null },
    outbound_flags: { static: [], dynamic: [] },
    dial_plan: { system: [], rules: [] },
    metaflows: {
      binding_digit: '*',
      digit_timeout: null,
      listen_on: 'both',
      number_flow_count: 0,
      pattern_flow_count: 0,
      actions: [],
      locked_action_count: 0,
    },
    flags: [],
    formatters: [],
    provision: {
      id: null,
      endpoint_model: null,
      check_sync_event: null,
      check_sync_reload: null,
      check_sync_reboot: null,
    },
    hotdesk: { active_user_count: 0 },
  }
}

export function hydrateDeviceConfiguration(
  source?: Partial<DeviceConfiguration>,
): DeviceConfiguration {
  const defaults = defaultDeviceConfiguration()

  if (!source) return defaults

  return {
    ...defaults,
    ...source,
    call_forward: { ...defaults.call_forward, ...source.call_forward },
    sip: {
      ...defaults.sip,
      ...source.sip,
      username: null,
      password: null,
      custom_sip_headers: {
        in: [...(source.sip?.custom_sip_headers?.in ?? [])],
        out: [...(source.sip?.custom_sip_headers?.out ?? [])],
      },
    },
    media: {
      ...defaults.media,
      ...source.media,
      audio: { ...defaults.media.audio, ...source.media?.audio },
      video: { ...defaults.media.video, ...source.media?.video },
      encryption: { ...defaults.media.encryption, ...source.media?.encryption },
    },
    caller_id: {
      ...defaults.caller_id,
      ...source.caller_id,
      internal: { ...defaults.caller_id.internal, ...source.caller_id?.internal },
      external: { ...defaults.caller_id.external, ...source.caller_id?.external },
      emergency: { ...defaults.caller_id.emergency, ...source.caller_id?.emergency },
      asserted: { ...defaults.caller_id.asserted, ...source.caller_id?.asserted },
    },
    caller_id_options: { ...defaults.caller_id_options, ...source.caller_id_options },
    call_waiting: { ...defaults.call_waiting, ...source.call_waiting },
    do_not_disturb: { ...defaults.do_not_disturb, ...source.do_not_disturb },
    contact_list: { ...defaults.contact_list, ...source.contact_list },
    ringtones: { ...defaults.ringtones, ...source.ringtones },
    call_restriction: { ...defaults.call_restriction, ...source.call_restriction },
    call_recording: {
      any: hydrateRecordingSource(defaults.call_recording.any, source.call_recording?.any),
      inbound: hydrateRecordingSource(
        defaults.call_recording.inbound,
        source.call_recording?.inbound,
      ),
      outbound: hydrateRecordingSource(
        defaults.call_recording.outbound,
        source.call_recording?.outbound,
      ),
    },
    music_on_hold: { ...defaults.music_on_hold, ...source.music_on_hold },
    outbound_flags: {
      static: [...(source.outbound_flags?.static ?? defaults.outbound_flags.static)],
      dynamic: [...(source.outbound_flags?.dynamic ?? defaults.outbound_flags.dynamic)],
    },
    dial_plan: {
      system: [...(source.dial_plan?.system ?? defaults.dial_plan.system)],
      rules: (source.dial_plan?.rules ?? defaults.dial_plan.rules).map((rule) => ({ ...rule })),
    },
    metaflows: {
      ...defaults.metaflows,
      ...source.metaflows,
      actions: (source.metaflows?.actions ?? []).map((action) => ({
        ...action,
        data: { ...action.data },
        children: hydrateMetaflowChildren(action.children),
      })),
    },
    flags: [...(source.flags ?? defaults.flags)],
    formatters: (source.formatters ?? defaults.formatters).map((formatter) => ({ ...formatter })),
    provision: { ...defaults.provision, ...source.provision },
    hotdesk: { ...defaults.hotdesk, ...source.hotdesk },
  }
}

function hydrateMetaflowChildren(
  children: DeviceConfiguration['metaflows']['actions'][number]['children'] | undefined,
): DeviceConfiguration['metaflows']['actions'][number]['children'] {
  return (children ?? []).map((child) => ({
    ...child,
    data: { ...child.data },
    children: hydrateMetaflowChildren(child.children),
  }))
}

function defaultRecordingParameters(): DeviceRecordingParameters {
  return {
    enabled: false,
    format: 'mp3',
    record_min_sec: null,
    record_on_answer: true,
    record_on_bridge: false,
    record_sample_rate: null,
    time_limit: null,
  }
}

function defaultRecordingSource(): DeviceRecordingSource {
  return {
    any: defaultRecordingParameters(),
    onnet: defaultRecordingParameters(),
    offnet: defaultRecordingParameters(),
  }
}

function hydrateRecordingSource(
  defaults: DeviceRecordingSource,
  source?: DeviceRecordingSource,
): DeviceRecordingSource {
  return {
    any: { ...defaults.any, ...source?.any },
    onnet: { ...defaults.onnet, ...source?.onnet },
    offnet: { ...defaults.offnet, ...source?.offnet },
  }
}

export function usesSip(deviceType: DeviceType): boolean {
  return ['sip_device', 'smartphone', 'softphone', 'fax', 'ata', 'sip_uri'].includes(deviceType)
}

export function usesForwarding(deviceType: DeviceType): boolean {
  return ['cellphone', 'smartphone', 'landline'].includes(deviceType)
}

export function isForwardingOnlyDevice(deviceType: DeviceType): boolean {
  return deviceType === 'cellphone' || deviceType === 'landline'
}

export function supportsVideo(deviceType: DeviceType): boolean {
  return ['sip_device', 'smartphone', 'softphone'].includes(deviceType)
}

export function supportsProvisioning(deviceType: DeviceType): boolean {
  return ['sip_device', 'fax', 'ata'].includes(deviceType)
}

export function supportsFaxOption(deviceType: DeviceType): boolean {
  return ['sip_device', 'softphone', 'fax', 'ata'].includes(deviceType)
}

export function supportsIgnoreCompletedElsewhere(deviceType: DeviceType): boolean {
  return deviceType === 'sip_device' || deviceType === 'softphone'
}

export function supportsOutboundFlags(deviceType: DeviceType): boolean {
  return ['sip_device', 'smartphone', 'softphone', 'ata'].includes(deviceType)
}

export function supportsMusicOnHold(deviceType: DeviceType): boolean {
  return deviceType === 'sip_device' || deviceType === 'softphone'
}

export type DeviceOptionCapability =
  'forwarding' | 'ringtones' | 'fax' | 'contact-list' | 'ignore-completed-elsewhere'

export const deviceOptionCapabilities: Record<DeviceType, ReadonlySet<DeviceOptionCapability>> = {
  sip_device: new Set(['ringtones', 'fax', 'contact-list', 'ignore-completed-elsewhere']),
  cellphone: new Set(['forwarding', 'contact-list']),
  smartphone: new Set(['forwarding', 'contact-list']),
  landline: new Set(['forwarding', 'contact-list']),
  softphone: new Set(['fax', 'contact-list', 'ignore-completed-elsewhere']),
  fax: new Set(['fax', 'contact-list']),
  ata: new Set(['fax', 'contact-list']),
  sip_uri: new Set(['contact-list']),
}

export function supportsDeviceOption(
  deviceType: DeviceType,
  capability: DeviceOptionCapability,
): boolean {
  return deviceOptionCapabilities[deviceType].has(capability)
}

export type DeviceFieldGroup = 'contact-list' | 'endpoint-behavior' | 'advanced-routing'

export const deviceFieldCapabilities: Record<DeviceType, ReadonlySet<DeviceFieldGroup>> = {
  sip_device: new Set(['contact-list', 'endpoint-behavior', 'advanced-routing']),
  cellphone: new Set(['contact-list']),
  smartphone: new Set(['contact-list', 'endpoint-behavior', 'advanced-routing']),
  softphone: new Set(['contact-list', 'endpoint-behavior', 'advanced-routing']),
  landline: new Set(['contact-list']),
  fax: new Set(['contact-list', 'endpoint-behavior', 'advanced-routing']),
  ata: new Set(['contact-list', 'endpoint-behavior', 'advanced-routing']),
  sip_uri: new Set(['contact-list']),
}

export function supportsDeviceFieldGroup(
  deviceType: DeviceType,
  fieldGroup: DeviceFieldGroup,
): boolean {
  return deviceFieldCapabilities[deviceType].has(fieldGroup)
}

export const deviceFormTabs: Record<DeviceType, DeviceFormTab[]> = {
  sip_device: ['basic', 'caller-id', 'sip', 'audio', 'video', 'options', 'restrictions'],
  cellphone: ['basic', 'options'],
  smartphone: ['basic', 'sip', 'options', 'restrictions'],
  softphone: ['basic', 'caller-id', 'sip', 'audio', 'video', 'options', 'restrictions'],
  landline: ['basic', 'options'],
  fax: ['basic', 'caller-id', 'sip', 'options', 'restrictions'],
  ata: ['basic', 'caller-id', 'sip', 'options', 'restrictions'],
  sip_uri: ['basic', 'options'],
}

export function deviceSupportsTab(deviceType: DeviceType, tab: DeviceFormTab): boolean {
  return deviceFormTabs[deviceType].includes(tab)
}

export function supportsDeviceRecording(deviceType: DeviceType): boolean {
  return ['sip_device', 'softphone'].includes(deviceType)
}

export function supportsDeviceNotifications(deviceType: DeviceType): boolean {
  return ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'].includes(deviceType)
}

export function isBasicDeviceErrorField(field: string): boolean {
  return (
    ['name', 'device_type', 'mac_address', 'assigned_extension_id', 'call_forward.number'].some(
      (path) => field === path || field.startsWith(`${path}.`),
    ) ||
    ['provision.endpoint_brand', 'provision.endpoint_family', 'provision.endpoint_model'].includes(
      field,
    )
  )
}

export function deviceAdvancedTabForError(field: string, deviceType?: DeviceType): string {
  if (field === 'sip.ignore_completed_elsewhere') return 'options'
  if (field.startsWith('outbound_flags.') && deviceType && deviceSupportsTab(deviceType, 'sip')) {
    return 'sip'
  }
  if (field.startsWith('music_on_hold.') && deviceType && deviceSupportsTab(deviceType, 'audio')) {
    return 'audio'
  }
  if (field.startsWith('sip.')) return 'sip'
  if (field.startsWith('media.video.')) return 'video'
  if (field.startsWith('media.')) return 'audio'
  if (field.startsWith('call_recording.')) return 'options'
  if (
    field.startsWith('music_on_hold.') ||
    field.startsWith('outbound_flags.') ||
    field.startsWith('dial_plan.') ||
    field.startsWith('metaflows.') ||
    field.startsWith('flags') ||
    field.startsWith('formatters.') ||
    field.startsWith('provision.check_sync_') ||
    field.startsWith('sip.custom_sip_headers.')
  ) {
    return 'options'
  }
  if (field.startsWith('caller_id')) return 'caller-id'
  if (field === 'presence_id' && deviceType && deviceSupportsTab(deviceType, 'caller-id')) {
    return 'caller-id'
  }
  if (field.startsWith('call_restriction.')) return 'restrictions'
  if (
    [
      'language',
      'timezone',
      'mwi_unsolicited_updates',
      'register_overwrite_notify',
      'suppress_unregister_notifications',
    ].some((path) => field === path || field.startsWith(`${path}.`)) ||
    field.startsWith('ringtones.')
  ) {
    return 'options'
  }

  return 'options'
}

export function hydrateDeviceRestrictions(
  restrictions: DeviceConfiguration['call_restriction'],
  options: DeviceRestrictionOption[],
): DeviceConfiguration['call_restriction'] {
  const hydrated: DeviceConfiguration['call_restriction'] = {
    closed_groups: { action: 'inherit' },
    ...restrictions,
  }

  for (const option of options) {
    hydrated[option.key] ??= { action: 'inherit' }
  }

  return hydrated
}
