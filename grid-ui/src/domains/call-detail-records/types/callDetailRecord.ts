export type SyncState = {
  status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_successful_at: string | null
  error_message: string | null
}

export type CallParty = {
  name: string | null
  number: string | null
}

export type CallExtension = {
  id: string
  display_name: string
  extension: string | null
}

export type CallDetailRecord = {
  id: string
  call_id: string
  interaction_id: string | null
  direction: 'inbound' | 'outbound' | null
  caller: CallParty
  callee: CallParty
  from: string | null
  to: string | null
  request: string | null
  started_at: string
  duration_seconds: number
  billing_seconds: number
  answered: boolean
  hangup_cause: string | null
  disposition: string | null
  recording_available: boolean
  recordings: Array<{
    id: string
    name: string | null
    duration_seconds: number
    has_audio: boolean
  }>
  extension: CallExtension | null
  last_synced_at: string
}

export type CallDetailRecordFilters = {
  search: string
  direction: '' | 'inbound' | 'outbound'
  outcome: '' | 'answered' | 'unanswered'
  hangup_cause: string
  started_from: string
  started_to: string
  started_after: string
  started_before: string
  duration_min: string
  duration_max: string
}

export type SyncRun = {
  id: string
  resource_type: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
