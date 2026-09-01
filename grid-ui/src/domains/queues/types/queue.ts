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
  announce_media: { id: string; name: string } | null
  max_priority: number | null
  announcements: {
    enabled: boolean
    interval: number
    position_announcements_enabled: boolean
    wait_time_announcements_enabled: boolean
    media: {
      in_the_queue: { id: string; name: string } | null
      increase_in_call_volume: { id: string; name: string } | null
      the_estimated_wait_time_is: { id: string; name: string } | null
      you_are_at_position: { id: string; name: string } | null
    }
  }
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
  announce_media_id: string | null
  max_priority: number | null
  announcements_enabled: boolean
  announcement_interval: number
  position_announcements_enabled: boolean
  wait_time_announcements_enabled: boolean
  announcement_in_the_queue_media_id: string | null
  announcement_increase_in_call_volume_media_id: string | null
  announcement_estimated_wait_time_media_id: string | null
  announcement_position_media_id: string | null
  agent_ids: string[]
}

export type { QueueOption, QueueOptions } from '../schemas/queueOptionsSchema'
export type { AgentAvailability, AgentAvailabilityStatus } from '../schemas/agentAvailabilitySchema'
export type { AgentStatistics, AgentStatisticsMetrics } from '../schemas/agentStatisticsSchema'
export type {
  AgentQueueMembership,
  AgentQueueMembershipInput,
} from '../schemas/agentQueueMembershipSchema'
export type { QueueStatistics, QueueStatisticsMetrics } from '../schemas/queueStatisticsSchema'
export type QueueSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
export type Agent = {
  id: string
  name: string
  extension: string | null
  queues: Array<{ id: string; name: string }>
}
export type AgentStatus = { id: string; status: string | null; timestamp: number | null }
export type AgentStatusInput = {
  status: 'login' | 'logout' | 'pause' | 'resume' | 'end_wrapup'
  pause_timeout?: number | null
}
