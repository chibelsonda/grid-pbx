export type DirectoryMember = {
  id: string
  extension: { id: string; label: string; number: string | null } | null
  callflow: { id: string; name: string | null } | null
  resolved: boolean
}

export type Directory = {
  id: string
  name: string
  confirm_match: boolean
  min_dtmf: number
  max_dtmf: number
  sort_by: 'first_name' | 'last_name'
  member_count?: number
  members?: DirectoryMember[]
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}

export type DirectoryInput = {
  name: string
  confirm_match: boolean
  min_dtmf: number
  max_dtmf: number
  sort_by: 'first_name' | 'last_name'
  member_ids: string[]
}

export type DirectoryOptions = {
  extensions: Array<{ id: string; label: string; detail: string | null }>
}

export type DirectorySyncRun = { id: string; status: 'queued' | 'running' | 'succeeded' | 'failed'; error_message: string | null }
