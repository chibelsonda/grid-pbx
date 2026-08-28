export type MediaDependencies = {
  music_on_hold: number
  voicemail_greetings: number
  callflows: number
  total: number
  can_delete: boolean
}

export type Media = {
  id: string
  name: string
  description: string | null
  language: string | null
  media_source: string | null
  content_type: string | null
  content_length: number | null
  prompt_id: string | null
  streamable: boolean
  is_music_on_hold: boolean
  dependencies?: MediaDependencies
  last_synced_at: string | null
  sync_status: string | null
  created_at: string | null
  updated_at: string | null
}

export type MediaFilters = { search: string; media_source: string }

export type MediaCreate = {
  name: string
  description: string | null
  language: string | null
  streamable: boolean
  audio: File
}

export type MediaUpdate = Omit<MediaCreate, 'audio'>

export type SyncState = {
  status: string
  last_successful_at: string | null
  error_message: string | null
}

export type SyncRun = { id: string; status: string; error_message: string | null }
