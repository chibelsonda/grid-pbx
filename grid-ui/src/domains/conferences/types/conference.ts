export type ConferenceOwner = { id: string; label: string | null; extension: string | null }
export type ConferenceMedia = { id: string; name: string }
export type ConferenceToneMode = 'enabled' | 'disabled' | 'media' | 'current_custom'
export type ConferenceTone = { mode: ConferenceToneMode; media: ConferenceMedia | null }
export type Conference = {
  id: string
  name: string
  owner: ConferenceOwner | null
  conference_numbers: string[]
  member_numbers: string[]
  moderator_numbers: string[]
  member_pin_configured: boolean
  moderator_pin_configured: boolean
  member_join_muted: boolean
  member_join_deaf: boolean
  member_play_entry_prompt: boolean
  moderator_join_muted: boolean
  moderator_join_deaf: boolean
  max_participants: number | null
  language: string | null
  profile_name: string | null
  caller_controls: string | null
  moderator_controls: string | null
  play_name: boolean
  play_welcome: boolean
  require_moderator: boolean
  wait_for_moderator: boolean
  max_members_media: ConferenceMedia | null
  entry_tone: ConferenceTone
  exit_tone: ConferenceTone
  runtime: { members: number; moderators: number; duration_seconds: number; is_locked: boolean }
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}
export type ConferenceInput = {
  name: string
  owner_id: string | null
  conference_numbers: string[]
  member_numbers: string[]
  moderator_numbers: string[]
  member_pins: string[]
  clear_member_pin: boolean
  moderator_pins: string[]
  clear_moderator_pin: boolean
  member_join_muted: boolean
  member_join_deaf: boolean
  member_play_entry_prompt: boolean
  moderator_join_muted: boolean
  moderator_join_deaf: boolean
  max_participants: number | null
  language: string | null
  profile_name: string | null
  caller_controls: string | null
  moderator_controls: string | null
  play_name: boolean
  play_welcome: boolean
  require_moderator: boolean
  wait_for_moderator: boolean
  max_members_media_id: string | null
  play_entry_tone_mode: ConferenceToneMode
  play_entry_tone_media_id: string | null
  play_exit_tone_mode: ConferenceToneMode
  play_exit_tone_media_id: string | null
}
export type ConferenceOption = { id: string; label: string; detail: string | null }
export type ConferenceOptions = { owners: ConferenceOption[]; media: ConferenceOption[] }
export type ConferenceSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
