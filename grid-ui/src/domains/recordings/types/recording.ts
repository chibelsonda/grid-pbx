export type RecordingParty = { name: string | null; number: string | null }
export type RecordingExtension = { id: string; display_name: string; extension: string | null }
export type Recording = {
  id: string
  call_id: string | null
  interaction_id: string | null
  direction: 'inbound' | 'outbound' | null
  caller: RecordingParty
  callee: RecordingParty
  from: string | null
  to: string | null
  request: string | null
  started_at: string
  duration_seconds: number
  duration_milliseconds: number
  name: string | null
  description: string | null
  content_type: string | null
  content_length: number | null
  media_source: string | null
  media_type: string | null
  source_type: string | null
  origin: string | null
  has_audio: boolean
  extension: RecordingExtension | null
  call_detail_record_id: string | null
  last_synced_at: string | null
  sync_status: string
}
export type RecordingFilters = {
  search: string
  direction: '' | 'inbound' | 'outbound'
  started_from: string
  started_to: string
  duration_min: string
  duration_max: string
  has_audio: '' | '1' | '0'
}
export type RecordingSyncState = {
  status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_successful_at: string | null
  error_message: string | null
}
export type RecordingSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
