import type { MetaflowAction, MetaflowResources } from '@/shared/switch/metaflows/types'

export type Account = {
  id: string
  name: string
  realm: string | null
  timezone: string | null
  enabled: boolean
  organization: {
    id: string
    name: string
  }
  organization_role: string | null
  permissions: {
    can_manage_extensions: boolean
    can_manage_devices: boolean
    can_manage_voicemail: boolean
    can_manage_call_routing: boolean
    can_manage_media: boolean
    can_sync_call_detail_records: boolean
    can_view_services: boolean
    can_manage_account_settings: boolean
    can_onboard_descendants: boolean
  }
}

export type AccountDetail = Pick<Account, 'id' | 'name' | 'realm' | 'timezone' | 'organization'> & {
  enabled: boolean
  resource_counts: {
    extensions: number
    devices: number
    phone_numbers: number
    callflows: number
    voicemail_boxes: number
    queues: number
    media: number
    recordings: number
  }
  configuration_boundaries: {
    identity_defaults: 'safe_fields_available'
    calling_defaults: 'safe_fields_available'
    advanced_routing: 'guided_rules_available'
    enable_disable: 'implemented_confirmed'
    billing_topup: 'provider_required'
  }
  configuration: {
    organization_name: string | null
    language: string | null
    call_waiting_enabled: boolean
    do_not_disturb_enabled: boolean
    outbound_privacy: 'full' | 'name' | 'number' | 'none' | null
    show_rate: boolean
    ringtone_internal: string | null
    ringtone_external: string | null
    caller_id: {
      internal: { name: string | null; number: string | null }
      external: AccountCallerIdNumber
      emergency: AccountCallerIdNumber
    }
    call_restriction: Record<string, AccountCallRestriction>
    call_recording: Partial<AccountCallRecording>
    dial_plan: AccountDialPlan
    formatters: AccountFormatter[]
    preflow: AccountPreflow
    metaflows: AccountMetaflows
  }
  options: { caller_id_numbers: AccountCallerIdNumberOption[] }
  projection: {
    status: string
    version: number
    last_synced_at: string | null
  }
  permissions: {
    can_manage_settings: boolean
  }
}

export type AccountSettingsInput = {
  name: string
  organization_name: string | null
  timezone: string | null
  language: string | null
  call_waiting_enabled: boolean
  do_not_disturb_enabled: boolean
  outbound_privacy: 'full' | 'name' | 'number' | 'none' | null
  show_rate: boolean
  ringtone_internal: string | null
  ringtone_external: string | null
  caller_id: {
    internal: { name: string | null; number: string | null }
    external: AccountCallerIdSelection
    emergency: AccountCallerIdSelection
  }
  call_restriction: Record<string, AccountCallRestriction>
  call_recording: AccountCallRecordingInput
  dial_plan: AccountDialPlan
  formatters: AccountFormatter[]
  preflow: AccountPreflowSelection
  metaflows: Pick<AccountMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>
}

export type AccountSettingsOptions = {
  restrictions: AccountRestrictionOption[]
  callflows: AccountCallflowOption[]
  metaflow_resources: MetaflowResources
}

export type AccountCallflowOption = {
  id: string
  name: string
  description: string | null
}

export type AccountRestrictionOption = {
  key: string
  label: string
  emergency: boolean
}

export type AccountCallRestriction = { action: 'inherit' | 'deny' }

export type AccountRecordingParameters = {
  enabled: boolean
  format: 'mp3' | 'wav'
  record_min_sec: number | null
  record_on_answer: boolean
  record_on_bridge: boolean
  record_sample_rate: 8000 | 16000 | 32000 | 48000 | null
  time_limit: number | null
}

export type AccountRecordingSource = {
  any: AccountRecordingParameters
  onnet: AccountRecordingParameters
  offnet: AccountRecordingParameters
}

export type AccountRecordingRules = {
  any: AccountRecordingSource
  inbound: AccountRecordingSource
  outbound: AccountRecordingSource
}

export type AccountCallRecording = {
  account: AccountRecordingRules
  endpoint: AccountRecordingRules
}

export type AccountCallRecordingInput = Partial<
  Record<
    keyof AccountCallRecording,
    Partial<
      Record<
        keyof AccountRecordingRules,
        Partial<Record<keyof AccountRecordingSource, AccountRecordingParameters>>
      >
    >
  >
>

export type AccountDialPlanRule = {
  pattern: string
  description: string | null
  prefix: string | null
  suffix: string | null
}

export type AccountDialPlan = {
  system: string[]
  rules: AccountDialPlanRule[]
}

export type AccountFormatter = {
  field: string
  direction: 'inbound' | 'outbound' | 'both' | null
  match_invite_format: boolean
  prefix: string | null
  regex: string | null
  strip: boolean
  suffix: string | null
  value: string | null
}

export type AccountPreflow = {
  callflow_id: string | null
  name: string | null
  unresolved: boolean
}

export type AccountPreflowSelection = {
  callflow_id: string | null
  preserve_callflow: boolean
}

export type AccountMetaflows = {
  binding_digit: string | null
  digit_timeout: number | null
  listen_on: 'both' | 'self' | 'peer' | null
  number_flow_count: number
  pattern_flow_count: number
  actions: MetaflowAction[]
  locked_action_count: number
}

export type AccountCallerIdNumber = {
  name: string | null
  phone_number_id: string | null
  number: string | null
  unresolved: boolean
}

export type AccountCallerIdSelection = {
  name: string | null
  phone_number_id: string | null
  preserve_number: boolean
}

export type AccountCallerIdNumberOption = {
  id: string
  number: string
  display_name: string | null
  e911_enabled: boolean
}
