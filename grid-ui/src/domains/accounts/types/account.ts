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
    advanced_routing: 'planned'
    enable_disable: 'implemented_confirmed'
    billing_topup: 'provider_required'
  }
  configuration: {
    organization_name: string | null
    language: string | null
    call_waiting_enabled: boolean
    do_not_disturb_enabled: boolean
    outbound_privacy: 'full' | 'name' | 'number' | 'none'
    show_rate: boolean
    ringtone_internal: string | null
    ringtone_external: string | null
    caller_id: {
      internal: { name: string | null; number: string | null }
      external: AccountCallerIdNumber
      emergency: AccountCallerIdNumber
    }
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
  outbound_privacy: 'full' | 'name' | 'number' | 'none'
  show_rate: boolean
  ringtone_internal: string | null
  ringtone_external: string | null
  caller_id: {
    internal: { name: string | null; number: string | null }
    external: AccountCallerIdSelection
    emergency: AccountCallerIdSelection
  }
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
