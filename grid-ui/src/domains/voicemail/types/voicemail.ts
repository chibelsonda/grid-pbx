export type ProjectionStatus = 'healthy' | 'syncing' | 'stale' | 'error'

export type AssignedExtension = {
  id: string
  display_name: string
  extension: string | null
}

export type VoicemailGreeting = {
  id: string
  type: 'unavailable'
  name: string | null
  description: string | null
  content_type: string | null
  content_length: number | null
  media_source: string | null
  streamable: boolean
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type VoicemailNotificationCallback = {
  disabled: boolean
  number: string | null
  attempts: number | null
  interval_s: number | null
  timeout_s: number | null
  schedule: number[]
}

export type VoicemailBoxConfiguration = {
  check_if_owner: boolean
  delete_after_notify: boolean
  include_message_on_notify: boolean
  include_transcription_on_notify: boolean
  media_extension: 'mp3' | 'mp4' | 'wav'
  not_configurable: boolean
  oldest_message_first: boolean
  save_after_notify: boolean
  skip_envelope: boolean
  skip_greeting: boolean
  skip_instructions: boolean
  is_voicemail_ff_rw_enabled: boolean
  seek_duration_ms: number
  notify_callback: VoicemailNotificationCallback | null
}

export type VoicemailBox = {
  id: string
  name: string | null
  mailbox: string | null
  timezone: string | null
  notification_emails: string[]
  transcribe: boolean
  require_pin: boolean
  pin_configured: boolean
  is_setup: boolean | null
  configuration: VoicemailBoxConfiguration
  message_counts: {
    total: number
    new: number
    saved: number
    deleted: number
  }
  unavailable_greeting: VoicemailGreeting | null
  assigned_extension: AssignedExtension | null
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type VoicemailMessage = {
  id: string
  folder: 'new' | 'saved' | 'deleted' | null
  caller_id_name: string | null
  caller_id_number: string | null
  from_address: string | null
  to_address: string | null
  length: number | null
  occurred_at: string | null
  transcription_result: string | null
  transcription_text: string | null
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type VoicemailMessageFolder = 'new' | 'saved' | 'deleted'

export type VoicemailMessageBulkResult = {
  folder: VoicemailMessageFolder
  succeeded: string[]
  failed: Array<{ id: string; reason: string }>
}

export type VoicemailBoxInput = VoicemailBoxConfiguration & {
  name: string
  mailbox: string
  assigned_extension_id: string | null
  timezone: string | null
  notification_emails: string[]
  transcribe: boolean
  require_pin: boolean
  pin: string | null
}

export type VoicemailBoxBasicForm = {
  name: string
  mailbox: string
  assigned_extension_id: string | null
  timezone: string | null
  notification_emails: string
  transcribe: boolean
  require_pin: boolean
  pin: string
}

export type SyncState = {
  status: ProjectionStatus
  last_successful_at: string | null
  error_message: string | null
}

export type ExtensionOption = {
  id: string
  display_name: string
  extension: string | null
}

export type VoicemailFormOptions = {
  account_defaults: { timezone: string | null }
  timezones: string[]
  extensions: ExtensionOption[]
  capabilities: {
    voicemail_transcription: {
      schema_supported: boolean
      runtime_available: boolean | null
      default_enabled: boolean | null
    }
  }
}
