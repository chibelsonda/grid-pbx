export type ProjectionStatus = 'healthy' | 'syncing' | 'stale' | 'error'

export type AssignedExtension = {
  id: string
  display_name: string
  extension: string | null
}

export type Device = {
  id: string
  name: string | null
  device_type: string | null
  make: string | null
  model: string | null
  mac_address: string | null
  is_enabled: boolean
  assigned_extension: AssignedExtension | null
  sync_status: ProjectionStatus
  last_synced_at: string | null
}

export type SyncState = {
  status: ProjectionStatus
  last_successful_at: string | null
  error_message: string | null
}

export type DeviceInput = {
  name: string
  device_type: string
  make: string | null
  model: string | null
  mac_address: string | null
  is_enabled: boolean
  assigned_extension_id: string | null
  sip_username: string | null
  sip_password: string | null
}

export type ExtensionOption = {
  id: string
  display_name: string
  extension: string | null
}
