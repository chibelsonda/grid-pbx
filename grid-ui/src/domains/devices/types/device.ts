export type ProjectionStatus = 'healthy' | 'syncing' | 'stale' | 'error'
export type RegistrationStatus = 'registered' | 'unregistered' | 'unknown'

export type DeviceType =
  'sip_device' | 'cellphone' | 'smartphone' | 'softphone' | 'landline' | 'fax' | 'ata' | 'sip_uri'

export type DeviceInviteFormat =
  'username' | 'npan' | '1npan' | 'e164' | 'route' | 'strip_plus' | 'contact'

export type DeviceBasicForm = {
  name: string
  device_type: DeviceType
  make: string
  family: string
  model: string
  mac_address: string
  is_enabled: boolean
  assigned_extension_id: string
}

export type DeviceFormTab =
  'basic' | 'caller-id' | 'sip' | 'audio' | 'video' | 'options' | 'restrictions'

export type CallerIdIdentity = {
  name: string | null
  number: string | null
}

export type DeviceSipHeader = { name: string; value: string }
export type DeviceDialPlanRule = {
  pattern: string
  description: string | null
  prefix: string | null
  suffix: string | null
}

export type DeviceFormatter = {
  field: string
  direction: 'inbound' | 'outbound' | 'both' | null
  match_invite_format: boolean
  prefix: string | null
  regex: string | null
  strip: boolean
  suffix: string | null
  value: string | null
}

export type DeviceMetaflowModule =
  | 'audio_level'
  | 'break'
  | 'callflow'
  | 'hangup'
  | 'hold_control'
  | 'move'
  | 'play'
  | 'record_call'
  | 'resume'
  | 'say'
  | 'sound_touch'
  | 'transfer'
  | 'tts'

export type DeviceMetaflowNode = {
  module: DeviceMetaflowModule
  data: Record<string, string | number | boolean | null>
  children: DeviceMetaflowChild[]
}

export type DeviceMetaflowChild = DeviceMetaflowNode & { key: string }
export type DeviceMetaflowAction = DeviceMetaflowNode & {
  trigger_type: 'number' | 'pattern'
  trigger: string
}

export type DeviceRecordingParameters = {
  enabled: boolean
  format: 'mp3' | 'wav'
  record_min_sec: number | null
  record_on_answer: boolean
  record_on_bridge: boolean
  record_sample_rate: 8000 | 16000 | 32000 | 48000 | null
  time_limit: number | null
}

export type DeviceRecordingSource = {
  any: DeviceRecordingParameters
  onnet: DeviceRecordingParameters
  offnet: DeviceRecordingParameters
}

export type DeviceConfiguration = {
  call_forward: {
    enabled: boolean
    number: string | null
    direct_calls_only: boolean
    failover: boolean
    ignore_early_media: boolean
    keep_caller_id: boolean
    require_keypress: boolean
    substitute: boolean
  }
  sip: {
    method: 'password' | 'ip'
    username: string | null
    password: string | null
    username_configured: boolean
    realm: string | null
    expire_seconds: number | null
    invite_format: DeviceInviteFormat
    ip: string | null
    number: string | null
    route: string | null
    static_route: string | null
    custom_sip_interface: string | null
    forward: string | null
    proxy: string | null
    static_invite: string | null
    transport: string | null
    ignore_completed_elsewhere: boolean
    custom_sip_headers: { in: DeviceSipHeader[]; out: DeviceSipHeader[] }
  }
  media: {
    audio: { codecs: string[] }
    video: { codecs: string[] }
    bypass_media: boolean | 'auto'
    encryption: {
      enforce_security: boolean
      methods: string[]
    }
    fax_option: boolean
    ignore_early_media: boolean
    progress_timeout: number | null
  }
  caller_id: {
    internal: CallerIdIdentity
    external: CallerIdIdentity
    emergency: CallerIdIdentity
    asserted: CallerIdIdentity & { realm: string | null }
  }
  caller_id_options: { outbound_privacy: 'full' | 'name' | 'number' | 'none' }
  call_waiting: { enabled: boolean }
  do_not_disturb: { enabled: boolean }
  contact_list: { exclude: boolean }
  exclude_from_queues: boolean
  language: string | null
  timezone: string | null
  presence_id: string | null
  mwi_unsolicited_updates: boolean
  register_overwrite_notify: boolean
  suppress_unregister_notifications: boolean
  ringtones: { internal: string | null; external: string | null }
  call_restriction: Record<string, { action: 'inherit' | 'deny' }>
  call_recording: {
    any: DeviceRecordingSource
    inbound: DeviceRecordingSource
    outbound: DeviceRecordingSource
  }
  music_on_hold: { media_id: string | null; media_name: string | null }
  outbound_flags: { static: string[]; dynamic: string[] }
  dial_plan: { system: string[]; rules: DeviceDialPlanRule[] }
  metaflows: {
    binding_digit: string | null
    digit_timeout: number | null
    listen_on: 'both' | 'self' | 'peer' | null
    number_flow_count: number
    pattern_flow_count: number
    actions: DeviceMetaflowAction[]
    locked_action_count: number
  }
  flags: string[]
  formatters: DeviceFormatter[]
  provision: {
    id: string | null
    endpoint_model: string | number | string[] | null
    check_sync_event: string | null
    check_sync_reload: string | null
    check_sync_reboot: string | null
  }
  hotdesk: { active_user_count: number }
}

export type AssignedExtension = {
  id: string
  display_name: string
  extension: string | null
}

export type Device = {
  id: string
  name: string | null
  device_type: DeviceType | null
  make: string | null
  endpoint_family?: string | null
  model: string | null
  mac_address: string | null
  is_enabled: boolean
  registration_status: RegistrationStatus
  registration_checked_at: string | null
  assigned_extension: AssignedExtension | null
  configuration?: Partial<DeviceConfiguration>
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

type DeviceSipCompatibilityField =
  'custom_sip_interface' | 'forward' | 'proxy' | 'static_invite' | 'transport'

export type FullDeviceSipInput = Omit<
  DeviceConfiguration['sip'],
  | 'username_configured'
  | 'ignore_completed_elsewhere'
  | 'custom_sip_headers'
  | DeviceSipCompatibilityField
> &
  Partial<
    Pick<
      DeviceConfiguration['sip'],
      'ignore_completed_elsewhere' | 'custom_sip_headers' | DeviceSipCompatibilityField
    >
  >

export type DeviceSipInput =
  FullDeviceSipInput | Pick<DeviceConfiguration['sip'], 'invite_format' | 'route'>

export type SyncState = {
  status: ProjectionStatus
  last_successful_at: string | null
  error_message: string | null
}

export type DeviceInput = {
  name: string
  device_type: DeviceType
  provision?: {
    id?: string | null
    endpoint_brand: string | null
    endpoint_family: string | null
    endpoint_model: string | number | string[] | null
    check_sync_event?: string | null
    check_sync_reload?: string | null
    check_sync_reboot?: string | null
  }
  mac_address?: string | null
  is_enabled: boolean
  assigned_extension_id: string | null
  call_forward?: Pick<
    DeviceConfiguration['call_forward'],
    'enabled' | 'number' | 'keep_caller_id' | 'require_keypress'
  >
  sip?: DeviceSipInput
  media?: DeviceConfiguration['media'] | Pick<DeviceConfiguration['media'], 'fax_option'>
  caller_id?: DeviceConfiguration['caller_id']
  caller_id_options?: DeviceConfiguration['caller_id_options']
  call_waiting?: DeviceConfiguration['call_waiting']
  do_not_disturb?: DeviceConfiguration['do_not_disturb']
  contact_list?: DeviceConfiguration['contact_list']
  exclude_from_queues?: boolean
  language?: string | null
  timezone?: string | null
  presence_id?: string | null
  mwi_unsolicited_updates?: boolean
  register_overwrite_notify?: boolean
  suppress_unregister_notifications?: boolean
  ringtones?: DeviceConfiguration['ringtones']
  call_restriction?: DeviceConfiguration['call_restriction']
  call_recording?: DeviceConfiguration['call_recording']
  music_on_hold?: Pick<DeviceConfiguration['music_on_hold'], 'media_id'>
  outbound_flags?: DeviceConfiguration['outbound_flags']
  dial_plan?: DeviceConfiguration['dial_plan']
  metaflows?: Pick<
    DeviceConfiguration['metaflows'],
    'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'
  >
  flags?: string[]
  formatters?: DeviceFormatter[]
}

export type ExtensionOption = {
  id: string
  display_name: string
  extension: string | null
}

export type DeviceHotdeskMemberships = {
  users: ExtensionOption[]
  unresolved_count: number
}

export type DeviceRestrictionOption = {
  key: string
  label: string
  emergency: boolean
}

export type DeviceCallerIdNumberOption = {
  id: string
  number: string
  display_name: string | null
  e911_enabled: boolean
}

export type DeviceProvisioningCatalog = {
  available: boolean
  reason: string | null
  brands: Array<{
    id: string
    name: string
    families: Array<{
      id: string
      name: string
      models: Array<{
        id: string
        name: string
        template_id: string | null
        max_keys?: number | null
        max_expansion_modules?: number | null
        keys_per_expansion_module?: number | null
        supported_key_types?: string[]
        value_sources?: string[]
        manufacturer_provider?: string | null
      }>
    }>
  }>
}

export type DeviceSchemaCompatibility = {
  source: 'connected_switch' | 'bundled_legacy_fallback'
  schema_id: string | null
  call_forward: { number_max_length: number }
  sip: {
    invite_formats: DeviceInviteFormat[]
    custom_sip_interface: boolean
    forward: boolean
    proxy: boolean
    static_invite: boolean
    transport: boolean
  }
  provision: {
    template_id: boolean
    endpoint_model_types: Array<'string' | 'integer' | 'array'>
    check_sync_event: boolean
    check_sync_reload: boolean
    check_sync_reboot: boolean
  }
}

export type DeviceOptions = {
  extensions: ExtensionOption[]
  media: Array<{ id: string; name: string | null }>
  metaflow_resources?: DeviceMetaflowResources
  caller_id_numbers: DeviceCallerIdNumberOption[]
  provisioning_catalog: DeviceProvisioningCatalog
  device_schema: DeviceSchemaCompatibility
  restrictions: DeviceRestrictionOption[]
}

export type DeviceMetaflowResources = {
  callflows: Array<{ id: string; name: string | null; description: string | null }>
  devices: Array<{ id: string; name: string | null }>
}
