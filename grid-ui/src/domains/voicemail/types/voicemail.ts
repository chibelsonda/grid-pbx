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

export type VoicemailBox = {
  id: string
  name: string | null
  mailbox: string | null
  timezone: string | null
  notification_emails: string[]
  transcribe: boolean
  require_pin: boolean
  is_setup: boolean | null
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

export type VoicemailBoxInput = {
  name: string
  mailbox: string
  assigned_extension_id: string | null
  timezone: string | null
  notification_emails: string[]
  transcribe: boolean
  require_pin: boolean
  pin: string | null
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
