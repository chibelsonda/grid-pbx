export type QueueAgent = {
  id: string
  agent: { id: string; name: string; extension: string | null } | null
  resolved: boolean
}

export type Queue = {
  id: string
  name: string
  strategy: 'round_robin' | 'most_idle'
  agent_count?: number
  agent_ring_timeout: number
  agent_wrapup_time: number
  connection_timeout: number
  max_queue_size: number
  ring_simultaneously: number
  enter_when_empty: boolean
  record_caller: boolean
  caller_exit_key: string
  music_on_hold_media: { id: string; name: string } | null
  agents?: QueueAgent[]
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}

export type QueueInput = {
  name: string
  strategy: 'round_robin' | 'most_idle'
  agent_ring_timeout: number
  agent_wrapup_time: number
  connection_timeout: number
  max_queue_size: number
  ring_simultaneously: number
  enter_when_empty: boolean
  record_caller: boolean
  caller_exit_key: string
  music_on_hold_media_id: string | null
  agent_ids: string[]
}

export type QueueOption = { id: string; label: string; detail: string | null }
export type QueueOptions = { agents: QueueOption[]; media: QueueOption[] }
export type QueueSyncRun = { id: string; status: 'queued' | 'running' | 'succeeded' | 'failed'; error_message: string | null }
export type Agent = { id: string; name: string; extension: string | null; queues: Array<{ id: string; name: string }> }
export type AgentStatus = { id: string; status: string | null; timestamp: number | null }
export type AgentStatusInput = { status: 'login' | 'logout' | 'pause' | 'resume' | 'end_wrapup'; pause_timeout?: number | null }
