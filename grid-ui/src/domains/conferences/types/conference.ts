export type ConferenceOwner = { id: string; label: string | null; extension: string | null }
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
  member_pin: string | null
  clear_member_pin: boolean
  moderator_pin: string | null
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
}
export type ConferenceOption = { id: string; label: string; detail: string | null }
export type ConferenceOptions = { owners: ConferenceOption[] }
export type ConferenceSyncRun = { id: string; status: 'queued' | 'running' | 'succeeded' | 'failed'; error_message: string | null }
