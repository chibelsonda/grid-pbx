export type ProjectionStatus = 'healthy' | 'syncing' | 'stale' | 'error'
export type SyncRunStatus = 'queued' | 'running' | 'succeeded' | 'failed'

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
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionDevice = {
  id: string
  name: string | null
  device_type: string | null
  make: string | null
  model: string | null
  mac_address: string | null
  is_enabled: boolean
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionVoicemailBox = {
  id: string
  name: string | null
  mailbox: string | null
  is_setup: boolean | null
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionCallflow = {
  id: string
  name: string | null
  numbers: string[]
  modules: string[]
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type ExtensionDetail = Extension & {
  devices: ExtensionDevice[]
  voicemail_boxes: ExtensionVoicemailBox[]
  callflows: ExtensionCallflow[]
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
