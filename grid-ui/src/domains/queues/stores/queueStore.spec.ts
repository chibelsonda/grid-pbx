import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { PaginatedResponse } from '@/shared/api/http'
import { queueApi } from '../api/queueApi'
import type {
  Agent,
  AgentQueueMembership,
  AgentStatistics,
  AgentStatus,
  Queue,
  QueueInput,
  QueueOptions,
  QueueStatistics,
  QueueSyncRun,
} from '../types/queue'
import { useQueueStore } from './queueStore'

vi.mock('../api/queueApi', () => ({
  queueApi: {
    list: vi.fn<() => Promise<PaginatedResponse<Queue>>>(),
    detail: vi.fn<() => Promise<Queue>>(),
    options: vi.fn<() => Promise<QueueOptions>>(),
    statistics: vi.fn<() => Promise<QueueStatistics>>(),
    agentStatistics: vi.fn<() => Promise<AgentStatistics>>(),
    create: vi.fn<() => Promise<Queue>>(),
    update: vi.fn<() => Promise<Queue>>(),
    remove: vi.fn<() => Promise<void>>(),
    agents: vi.fn<() => Promise<Agent[]>>(),
    agentStatus: vi.fn<() => Promise<AgentStatus>>(),
    agentQueueMemberships: vi.fn<() => Promise<AgentQueueMembership>>(),
    updateAgentQueueMembership: vi.fn<() => Promise<AgentQueueMembership>>(),
    updateAgentStatus: vi.fn<() => Promise<void>>(),
    startSync: vi.fn<() => Promise<QueueSyncRun>>(),
    syncStatus: vi.fn<() => Promise<QueueSyncRun>>(),
  },
}))

const record: Queue = {
  id: 'public-queue',
  name: 'Support',
  strategy: 'round_robin',
  agent_count: 1,
  agent_ring_timeout: 15,
  agent_wrapup_time: 0,
  connection_timeout: 3600,
  max_queue_size: 0,
  ring_simultaneously: 1,
  enter_when_empty: true,
  record_caller: false,
  caller_exit_key: '#',
  music_on_hold_media: null,
  announce_media: null,
  max_priority: null,
  announcements: {
    enabled: false,
    interval: 30,
    position_announcements_enabled: false,
    wait_time_announcements_enabled: false,
    media: {
      in_the_queue: null,
      increase_in_call_volume: null,
      the_estimated_wait_time_is: null,
      you_are_at_position: null,
    },
  },
  agents: [],
  sync_status: 'healthy',
  last_synced_at: null,
}
const input: QueueInput = {
  name: 'Support',
  strategy: 'round_robin',
  agent_ring_timeout: 15,
  agent_wrapup_time: 0,
  connection_timeout: 3600,
  max_queue_size: 0,
  ring_simultaneously: 1,
  enter_when_empty: true,
  record_caller: false,
  caller_exit_key: '#',
  music_on_hold_media_id: null,
  announce_media_id: null,
  max_priority: null,
  announcements_enabled: false,
  announcement_interval: 30,
  position_announcements_enabled: false,
  wait_time_announcements_enabled: false,
  announcement_in_the_queue_media_id: null,
  announcement_increase_in_call_volume_media_id: null,
  announcement_estimated_wait_time_media_id: null,
  announcement_position_media_id: null,
  agent_ids: ['public-agent'],
}
const options: QueueOptions = {
  agents: [],
  media: [],
  capabilities: {
    configuration_available: true,
    live_agent_controls_available: false,
    agent_statistics_available: false,
    statistics_available: false,
  },
}
const agent: Agent = {
  id: 'public-agent',
  name: 'Ada',
  extension: '1001',
  queues: [{ id: record.id, name: record.name }],
}
const statistics: QueueStatistics = {
  observed_at: '2026-09-01T04:05:06+00:00',
  totals: {
    waiting: 1,
    handled: 1,
    abandoned: 0,
    processed: 2,
    average_wait_seconds: 12,
    average_talk_seconds: 90,
    longest_current_wait_seconds: 25,
  },
  queues: [],
  unresolved_records: 0,
}
const agentStatistics: AgentStatistics = {
  observed_at: '2026-09-01T04:05:06+00:00',
  totals: {
    total_calls: 10,
    answered_calls: 8,
    missed_calls: 2,
    answer_rate_percentage: 80,
  },
  agents: [],
  unresolved_agents: 0,
}
const agentQueueMembership: AgentQueueMembership = {
  agent: { id: agent.id, name: agent.name, extension: agent.extension },
  assigned_queues: agent.queues,
  available_queues: [
    { id: '22222222-2222-4222-8222-222222222222', name: 'Sales' },
  ],
  unresolved_queues: 0,
  observed_at: '2026-09-01T04:05:06+00:00',
}

describe('queue store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })
  it('loads account-scoped queues and projected agents together', async () => {
    vi.mocked(queueApi.list).mockResolvedValue({
      data: [record],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
    })
    vi.mocked(queueApi.agents).mockResolvedValue([
      {
        id: 'public-agent',
        name: 'Ada',
        extension: '1001',
        queues: [{ id: record.id, name: record.name }],
      },
    ])
    vi.mocked(queueApi.options).mockResolvedValue(options)
    const store = useQueueStore()
    await store.load('account-1')
    expect(queueApi.list).toHaveBeenCalledWith('account-1', '', 1)
    expect(store.records).toEqual([record])
    expect(store.agents).toHaveLength(1)
    expect(store.options.capabilities).toEqual(options.capabilities)
    expect(queueApi.statistics).not.toHaveBeenCalled()
    expect(queueApi.agentStatistics).not.toHaveBeenCalled()
  })
  it('creates a queue using public agent references', async () => {
    vi.mocked(queueApi.options).mockResolvedValue(options)
    vi.mocked(queueApi.create).mockResolvedValue(record)
    vi.mocked(queueApi.list).mockResolvedValue({
      data: [record],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
    })
    vi.mocked(queueApi.agents).mockResolvedValue([])
    const store = useQueueStore()
    await store.prepare('account-1')
    expect(await store.save('account-1', input)).toBe(true)
    expect(queueApi.create).toHaveBeenCalledWith('account-1', input)
  })
  it('keeps API validation errors inline without a duplicate mutation alert', async () => {
    vi.mocked(queueApi.create).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Invalid.', errors: { name: ['Enter a queue name.'] } } },
    })
    const store = useQueueStore()

    expect(await store.save('account-1', { ...input, name: '' })).toBe(false)
    expect(store.fieldErrors.name).toEqual(['Enter a queue name.'])
    expect(store.mutationError).toBeNull()
  })
  it('refreshes the selected agent from live Switch state without overlapping requests', async () => {
    let resolveStatus: ((status: AgentStatus) => void) | undefined
    vi.mocked(queueApi.agentStatus).mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveStatus = resolve
        }),
    )
    const store = useQueueStore()
    store.selectedAgent = agent

    const refresh = store.refreshAgentStatus('account-1')
    expect(store.statusRefreshing).toBe(true)
    expect(await store.refreshAgentStatus('account-1')).toBe(false)
    expect(queueApi.agentStatus).toHaveBeenCalledTimes(1)

    resolveStatus?.({ id: agent.id, status: 'ready', timestamp: 63800000000 })
    expect(await refresh).toBe(true)
    expect(store.agentStatus?.status).toBe('ready')
    expect(store.statusLastObservedAt).not.toBeNull()
    expect(store.statusRefreshing).toBe(false)
  })
  it('keeps the last observed state when a background refresh fails', async () => {
    vi.mocked(queueApi.agentStatus).mockRejectedValue(new Error('private provider failure'))
    const store = useQueueStore()
    store.selectedAgent = agent
    store.agentStatus = { id: agent.id, status: 'paused', timestamp: 63800000000 }

    expect(await store.refreshAgentStatus('account-1')).toBe(false)
    expect(store.agentStatus.status).toBe('paused')
    expect(store.statusRefreshError).toBe('Unable to refresh live agent status.')
    expect(store.mutationError).toBeNull()
  })
  it('records command acceptance without claiming the requested status was observed', async () => {
    vi.mocked(queueApi.updateAgentStatus).mockResolvedValue()
    const store = useQueueStore()
    store.selectedAgent = agent
    store.agentStatus = { id: agent.id, status: 'connected', timestamp: 63800000000 }

    expect(await store.updateAgentStatus('account-1', { status: 'pause', pause_timeout: 60 })).toBe(
      true,
    )
    expect(store.agentStatus.status).toBe('connected')
    expect(store.statusCommandAccepted).toBe(true)
  })
  it('reconciles the selected Agent after Switch accepts a Queue membership change', async () => {
    const updatedMembership: AgentQueueMembership = {
      ...agentQueueMembership,
      assigned_queues: [...agentQueueMembership.assigned_queues, agentQueueMembership.available_queues[0]!],
      available_queues: [],
    }
    vi.mocked(queueApi.updateAgentQueueMembership).mockResolvedValue(updatedMembership)
    const store = useQueueStore()
    store.selectedAgent = { ...agent, queues: [...agent.queues] }
    store.agents = [store.selectedAgent]

    expect(
      await store.updateAgentQueueMembership('account-1', {
        action: 'login',
        queue_id: '22222222-2222-4222-8222-222222222222',
      }),
    ).toBe(true)
    expect(queueApi.updateAgentQueueMembership).toHaveBeenCalledWith(
      'account-1',
      agent.id,
      { action: 'login', queue_id: '22222222-2222-4222-8222-222222222222' },
    )
    expect(store.selectedAgent.queues).toEqual(updatedMembership.assigned_queues)
    expect(store.membershipCommandAccepted).toBe(true)
  })
  it('refreshes an available Queue statistics snapshot', async () => {
    vi.mocked(queueApi.statistics).mockResolvedValue(statistics)
    const store = useQueueStore()

    expect(await store.refreshStatistics('account-1', true)).toBe(true)
    expect(queueApi.statistics).toHaveBeenCalledWith('account-1')
    expect(store.statistics).toEqual(statistics)
    expect(store.statisticsLoading).toBe(false)
  })

  it('retains the last metrics when a background refresh fails', async () => {
    vi.mocked(queueApi.statistics).mockRejectedValue(new Error('private Switch failure'))
    const store = useQueueStore()
    store.statistics = statistics

    expect(await store.refreshStatistics('account-1')).toBe(false)
    expect(store.statistics).toEqual(statistics)
    expect(store.statisticsError).toBe('Unable to refresh live queue activity.')
    expect(store.statisticsRefreshing).toBe(false)
  })

  it('refreshes available agent performance and retains it after a safe failure', async () => {
    vi.mocked(queueApi.agentStatistics).mockResolvedValueOnce(agentStatistics)
    const store = useQueueStore()

    expect(await store.refreshAgentStatistics('account-1', true)).toBe(true)
    expect(store.agentStatistics).toEqual(agentStatistics)

    vi.mocked(queueApi.agentStatistics).mockRejectedValueOnce(new Error('private Switch failure'))
    expect(await store.refreshAgentStatistics('account-1')).toBe(false)
    expect(store.agentStatistics).toEqual(agentStatistics)
    expect(store.agentStatisticsError).toBe('Unable to refresh live agent performance.')
    expect(store.agentStatisticsRefreshing).toBe(false)
  })
})
