export type CallerIdListEntry = {
  id: string
  display_name: string | null
  number: string | null
  pattern: string | null
}

export type CallerIdList = {
  id: string
  name: string
  description: string | null
  organization: string | null
  entry_count?: number
  entries?: CallerIdListEntry[]
  sync_status: string | null
  last_synced_at: string | null
}

export type CallerIdListEntryInput = {
  id: string | null
  display_name: string | null
  number: string | null
  pattern: string | null
}

export type CallerIdListInput = {
  name: string
  description: string | null
  organization: string | null
  entries: CallerIdListEntryInput[]
}

export type CallerIdListSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
