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
  configuration: ExtensionUserConfiguration & {
    credentials: ExtensionCredentialsProfile
    hotdesk: ExtensionHotdeskProfile
  }
  devices: ExtensionDevice[]
  voicemail_boxes: ExtensionVoicemailBox[]
  callflows: ExtensionCallflow[]
}

export type ExtensionCreate = ExtensionUserConfiguration & {
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
    notification_emails: string[]
    transcribe: boolean
    require_pin: boolean
    pin: string | null
  }
  device: {
    enabled: boolean
    name: string | null
    device_type: string | null
    mac_address: string | null
    sip_username: string | null
    sip_password: string | null
  }
}

export type ExtensionUpdate = Omit<ExtensionCreate, 'device'>

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
