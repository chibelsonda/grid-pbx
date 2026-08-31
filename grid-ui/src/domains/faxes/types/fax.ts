export type FaxOwner = { id: string; label: string | null; extension: string | null }
export type FaxBox = {
  id: string
  name: string
  owner: FaxOwner | null
  caller_id: string | null
  caller_name: string | null
  fax_header: string | null
  fax_identity: string | null
  fax_timezone: string | null
  retries: number
  t38_enabled: boolean
  smtp_email_address: string | null
  custom_smtp_email_address: string | null
  smtp_permission_list: string[]
  inbound_notification_emails: string[]
  outbound_notification_emails: string[]
  fax_count?: number
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}
export type FaxBoxInput = {
  name: string
  owner_id: string | null
  caller_id: string | null
  caller_name: string | null
  fax_header: string | null
  fax_identity: string | null
  fax_timezone: string | null
  retries: number
  t38_enabled: boolean
  custom_smtp_email_address: string | null
  smtp_permission_list: string[]
  inbound_notification_emails: string[]
  outbound_notification_emails: string[]
}
export type Fax = {
  id: string
  folder: 'inbox' | 'outbox'
  status: string | null
  fax_box: { id: string; name: string } | null
  owner: FaxOwner | null
  from: { name: string | null; number: string | null }
  to: { name: string | null; number: string | null }
  subject: string | null
  attempts: number
  retries: number
  successful: boolean | null
  error_message: string | null
  pages: number
  fax_speed: number
  elapsed_seconds: number
  created_at: string | null
  has_document: boolean
  document_content_type: string | null
  document_size: number | null
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}
export type FaxOperationCapability = {
  switch_supported: true
  enabled: false
  reason: string
}
export type FaxOperationCapabilities = {
  send: FaxOperationCapability
  forward: FaxOperationCapability
  resubmit: FaxOperationCapability
  delete_message: FaxOperationCapability
  delete_document: FaxOperationCapability
}
export type FaxOption = { id: string; label: string; detail: string | null }
export type FaxBoxOptions = {
  owners: FaxOption[]
  caller_id_numbers: string[]
  timezones: string[]
  account_defaults: { timezone: string | null }
}
export type FaxSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
