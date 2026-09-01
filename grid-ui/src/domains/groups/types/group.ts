export type GroupMemberType = 'user' | 'device' | 'group'
export type GroupMember = {
  id: string
  type: GroupMemberType
  weight: number
  target: { id: string; label: string; detail: string | null } | null
  resolved: boolean
}
export type Group = {
  id: string
  name: string
  member_count?: number
  music_on_hold_media: { id: string; name: string } | null
  members?: GroupMember[]
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}
export type GroupInput = {
  name: string
  music_on_hold_media_id: string | null
  members: Array<{ type: GroupMemberType; id: string; weight: number }>
}
export type GroupOption = { id: string; label: string; detail: string | null }
export type GroupOptions = {
  users: GroupOption[]
  devices: GroupOption[]
  groups: GroupOption[]
  media: GroupOption[]
}
export type GroupSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
