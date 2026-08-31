export type MenuMedia = { id: string; name: string }

export type Menu = {
  id: string
  name: string
  timeout: number
  interdigit_timeout: number
  max_extension_length: number
  retries: number
  hunt: boolean
  allow_record_from_offnet: boolean
  suppress_media: boolean
  record_pin_configured: boolean
  hunt_allow: string | null
  hunt_deny: string | null
  greeting_media: MenuMedia | null
  greeting_media_unresolved: boolean
  invalid_media_enabled: boolean
  invalid_media: MenuMedia | null
  invalid_media_unresolved: boolean
  transfer_media_enabled: boolean
  transfer_media: MenuMedia | null
  transfer_media_unresolved: boolean
  exit_media_enabled: boolean
  exit_media: MenuMedia | null
  exit_media_unresolved: boolean
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}

export type MenuInput = {
  name: string
  timeout: number
  interdigit_timeout: number
  max_extension_length: number
  retries: number
  hunt: boolean
  allow_record_from_offnet: boolean
  suppress_media: boolean
  record_pin: string | null
  clear_record_pin: boolean
  hunt_allow: string | null
  hunt_deny: string | null
  greeting_media_id: string | null
  clear_greeting_media: boolean
  invalid_media_enabled: boolean
  invalid_media_id: string | null
  clear_invalid_media: boolean
  transfer_media_enabled: boolean
  transfer_media_id: string | null
  clear_transfer_media: boolean
  exit_media_enabled: boolean
  exit_media_id: string | null
  clear_exit_media: boolean
}

export type MenuOption = { id: string; label: string; detail: string | null }
export type MenuOptions = { media: MenuOption[] }
export type MenuSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
