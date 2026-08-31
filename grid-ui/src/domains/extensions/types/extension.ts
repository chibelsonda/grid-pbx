export type ProjectionStatus = 'healthy' | 'syncing' | 'stale' | 'error'
export type SyncRunStatus = 'queued' | 'running' | 'succeeded' | 'failed'

export type ExtensionFormChoice = { value: string; label: string }

export type ExtensionFormOptions = {
  account_defaults: { timezone: string | null }
  timezones: string[]
  languages: ExtensionFormChoice[]
  presence_ids: ExtensionFormChoice[]
  starter_device: {
    supported_types: string[]
    provisionable_types: string[]
    sip_credential_types: string[]
  }
  caller_id_numbers: ExtensionCallerIdNumberOption[]
  media: Array<{ id: string; name: string | null }>
  restrictions: ExtensionRestrictionOption[]
  metaflow_resources: MetaflowResources
}

export type Extension = {
  id: string
  display_name: string
  first_name: string | null
  last_name: string | null
  username: string | null
  email: string | null
  extension: string | null
  timezone: string | null
  is_enabled: boolean
  is_managed: boolean
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionUserConfiguration = {
  language: string | null
  presence_id: string | null
  call_waiting: { enabled: boolean }
  do_not_disturb: { enabled: boolean }
  contact_list: { exclude: boolean }
  caller_id_options: { outbound_privacy: 'full' | 'name' | 'number' | 'none' }
}

export type ExtensionCallerIdNumber = {
  name: string | null
  phone_number_id: string | null
  number: string | null
  unresolved: boolean
}

export type ExtensionCallerIdSelection = {
  name: string | null
  phone_number_id: string | null
  preserve_number: boolean
}

export type ExtensionCallerIdNumberOption = {
  id: string
  number: string
  display_name: string | null
  e911_enabled: boolean
}

export type ExtensionRestrictionOption = { key: string; label: string; emergency: boolean }
export type ExtensionCallRestriction = { action: 'inherit' | 'deny' }

export type ExtensionCallForward = {
  enabled: boolean
  number: string | null
  direct_calls_only: boolean
  failover: boolean
  ignore_early_media: boolean
  keep_caller_id: boolean
  require_keypress: boolean
  substitute: boolean
}

export type ExtensionRecordingParameters = {
  enabled: boolean
  format: 'mp3' | 'wav'
  record_min_sec: number | null
  record_on_answer: boolean
  record_on_bridge: boolean
  record_sample_rate: 8000 | 16000 | 32000 | 48000 | null
  time_limit: number | null
}

export type ExtensionRecordingSource = Record<
  'any' | 'onnet' | 'offnet',
  ExtensionRecordingParameters
>
export type ExtensionRecordingRules = Record<
  'any' | 'inbound' | 'outbound',
  ExtensionRecordingSource
>
export type ExtensionCallRecording = ExtensionRecordingRules

export type ExtensionAdvancedCallingConfiguration = {
  caller_id: {
    internal: { name: string | null; number: string | null }
    external: ExtensionCallerIdNumber
    emergency: ExtensionCallerIdNumber
  }
  call_forward: ExtensionCallForward
  call_restriction: Record<string, ExtensionCallRestriction>
  call_recording: Partial<ExtensionCallRecording>
  media: ExtensionEndpointMedia
  music_on_hold: ExtensionMusicOnHold
  ringtones: ExtensionRingtones
  dial_plan: ExtensionDialPlan
  formatters: ExtensionFormatter[]
  profile: ExtensionProfile
  pronounced_name: ExtensionPronouncedName
}

export type ExtensionEndpointMedia = {
  audio: { codecs: string[] }
  video: { codecs: string[] }
  bypass_media: boolean | 'auto'
  encryption: { enforce_security: boolean; methods: Array<'srtp' | 'zrtp'> }
  fax_option: boolean
  ignore_early_media: boolean
  progress_timeout: number | null
}

export type ExtensionMusicOnHold = {
  media_id: string | null
  configured: boolean
  unresolved: boolean
}

export type ExtensionMusicOnHoldInput = {
  media_id: string | null
  preserve_media: boolean
}

export type ExtensionRingtones = { internal: string | null; external: string | null }

export type ExtensionDialPlanRule = {
  pattern: string
  description: string | null
  prefix: string | null
  suffix: string | null
}

export type ExtensionDialPlan = { system: string[]; rules: ExtensionDialPlanRule[] }

export type ExtensionFormatter = {
  field: string
  direction: 'inbound' | 'outbound' | 'both' | null
  match_invite_format: boolean
  prefix: string | null
  regex: string | null
  strip: boolean
  suffix: string | null
  value: string | null
}

export type ExtensionProfileAddressType =
  'dom' | 'postal' | 'intl' | 'parcel' | 'home' | 'work' | 'pref'

export type ExtensionProfile = {
  addresses: Array<{ address: string; types: ExtensionProfileAddressType[] }>
  assistant: string | null
  birthday: string | null
  nicknames: string[]
  note: string | null
  role: string | null
  sort_string: string | null
  title: string | null
}

export type ExtensionPronouncedName = {
  media_id: string | null
  configured: boolean
  unresolved: boolean
}

export type ExtensionPronouncedNameInput = {
  media_id: string | null
  preserve_media: boolean
}

export type ExtensionMetaflows = {
  binding_digit: string | null
  digit_timeout: number | null
  listen_on: 'both' | 'self' | 'peer' | null
  number_flow_count: number
  pattern_flow_count: number
  actions: MetaflowAction[]
  locked_action_count: number
}

export type ExtensionHotdeskProfile = {
  enabled: boolean
  id: string | null
  keep_logged_in_elsewhere: boolean
  require_pin: boolean
  pin_configured: boolean
}

export type ExtensionCredentialsProfile = {
  password_configured: boolean
  require_password_update: boolean
}

export type ExtensionCredentialsInput = {
  username: string | null
  password: string | null
  password_confirmation: string | null
  require_password_update: boolean
  clear_credentials: boolean
}

export type ExtensionHotdeskInput = Omit<ExtensionHotdeskProfile, 'pin_configured'> & {
  pin: string | null
  clear_pin: boolean
}

export type ExtensionCoreAdvancedInput = {
  caller_id: {
    internal: { name: string | null; number: string | null }
    external: ExtensionCallerIdSelection
    emergency: ExtensionCallerIdSelection
  }
  call_forward: ExtensionCallForward
  call_restriction: Record<string, ExtensionCallRestriction>
  call_recording: ExtensionCallRecording
}

export type ExtensionDevice = {
  id: string
  name: string | null
  device_type: string | null
  make: string | null
  model: string | null
  mac_address: string | null
  is_enabled: boolean
  is_managed: boolean
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionVoicemailBox = {
  id: string
  name: string | null
  mailbox: string | null
  is_setup: boolean | null
  timezone: string | null
  notification_emails: string[]
  transcribe: boolean
  require_pin: boolean
  message_count: number
  is_managed: boolean
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionCallflow = {
  id: string
  name: string | null
  numbers: string[]
  modules: string[]
  is_managed: boolean
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionDetail = Extension & {
  configuration: ExtensionUserConfiguration &
    ExtensionAdvancedCallingConfiguration & {
      credentials: ExtensionCredentialsProfile
      hotdesk: ExtensionHotdeskProfile
      metaflows: ExtensionMetaflows
      policy: {
        verified: boolean
        privilege: 'user' | 'admin' | null
        feature_level: string | null
        external_flag_count: number
      }
    }
  devices: ExtensionDevice[]
  voicemail_boxes: ExtensionVoicemailBox[]
  callflows: ExtensionCallflow[]
}

export type ExtensionCreate = ExtensionUserConfiguration &
  ExtensionCoreAdvancedInput & {
    first_name: string
    last_name: string
    extension: string
    username: string | null
    password: string | null
    password_confirmation: string | null
    require_password_update: boolean
    clear_credentials: boolean
    email: string | null
    timezone: string | null
    is_enabled: boolean
    hotdesk: ExtensionHotdeskInput
    voicemail: {
      enabled: boolean
      input: VoicemailBoxInput | null
    }
    device: {
      enabled: boolean
      input: DeviceInput | null
    }
  }

export type ExtensionUpdate = Omit<ExtensionCreate, 'device' | 'voicemail'> & {
  voicemail: {
    enabled: boolean
    input: VoicemailBoxInput | null
  }
  metaflows: Pick<ExtensionMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>
  media: ExtensionEndpointMedia
  music_on_hold: ExtensionMusicOnHoldInput
  ringtones: ExtensionRingtones
  dial_plan: ExtensionDialPlan
  formatters: ExtensionFormatter[]
  profile: ExtensionProfile
  pronounced_name: ExtensionPronouncedNameInput
}

export type ExtensionDeletionPreview = {
  extension: {
    id: string
    display_name: string
    extension: string | null
    managed: boolean
  }
  can_delete: boolean
  blockers: Array<{ code: string; message: string }>
  managed_resources: {
    devices: Array<{ id: string; name: string | null }>
    voicemail_boxes: Array<{
      id: string
      name: string | null
      mailbox: string | null
      message_count: number
    }>
    callflows: Array<{
      id: string
      name: string | null
      numbers: string[]
      phone_number_count: number
    }>
  }
  shared_resources: {
    device_count: number
    voicemail_box_count: number
    callflow_count: number
  }
  referencing_callflows: Array<{ id: string; name: string }>
  unresolved_callflows: Array<{ id: string; name: string }>
  recovery: {
    id: string
    completed_steps: string[]
    failed_step: string | null
    repair_required: boolean
  } | null
}

export type ExtensionRecoveryOperation = {
  id: string
  operation: 'provision' | 'update' | 'delete'
  status: 'failed' | 'running' | 'recovered' | 'succeeded' | 'rolled_back'
  display_name: string
  extension: string | null
  extension_id: string | null
  completed_steps: string[]
  failed_step: string | null
  recovery_action: 'cleanup' | 'reconcile' | 'resume' | 'unsupported'
  repair_required: boolean
  updated_at: string | null
}

export type SyncState = {
  status: ProjectionStatus
  last_successful_at: string | null
  error_message: string | null
}

export type SyncRun = {
  id: string
  status: SyncRunStatus
  processed_count: number
  upserted_count: number
  deleted_count: number
  error_message: string | null
  created_at: string
}
import type { MetaflowAction, MetaflowResources } from '@/shared/switch/metaflows/types'
import type { DeviceInput } from '@/domains/devices/types/device'
import type { VoicemailBoxInput } from '@/domains/voicemail/types/voicemail'
