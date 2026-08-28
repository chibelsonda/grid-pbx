import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { PaginatedResponse } from '@/shared/api/http'
import { queueApi } from '../api/queueApi'
import type { Agent, AgentStatus, Queue, QueueInput, QueueOptions, QueueSyncRun } from '../types/queue'
import { useQueueStore } from './queueStore'

vi.mock('../api/queueApi', () => ({ queueApi: {
  list: vi.fn<() => Promise<PaginatedResponse<Queue>>>(), detail: vi.fn<() => Promise<Queue>>(),
  options: vi.fn<() => Promise<QueueOptions>>(), create: vi.fn<() => Promise<Queue>>(),
  update: vi.fn<() => Promise<Queue>>(), remove: vi.fn<() => Promise<void>>(),
  agents: vi.fn<() => Promise<Agent[]>>(), agentStatus: vi.fn<() => Promise<AgentStatus>>(),
  updateAgentStatus: vi.fn<() => Promise<void>>(), startSync: vi.fn<() => Promise<QueueSyncRun>>(),
  syncStatus: vi.fn<() => Promise<QueueSyncRun>>(),
} }))

const record: Queue = { id: 'public-queue', name: 'Support', strategy: 'round_robin', agent_count: 1, agent_ring_timeout: 15, agent_wrapup_time: 0, connection_timeout: 3600, max_queue_size: 0, ring_simultaneously: 1, enter_when_empty: true, record_caller: false, caller_exit_key: '#', music_on_hold_media: null, agents: [], sync_status: 'healthy', last_synced_at: null }
const input: QueueInput = { name: 'Support', strategy: 'round_robin', agent_ring_timeout: 15, agent_wrapup_time: 0, connection_timeout: 3600, max_queue_size: 0, ring_simultaneously: 1, enter_when_empty: true, record_caller: false, caller_exit_key: '#', music_on_hold_media_id: null, agent_ids: ['public-agent'] }

describe('queue store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })
  it('loads account-scoped queues and projected agents together', async () => {
    vi.mocked(queueApi.list).mockResolvedValue({ data: [record], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 } })
    vi.mocked(queueApi.agents).mockResolvedValue([{ id: 'public-agent', name: 'Ada', extension: '1001', queues: [{ id: record.id, name: record.name }] }])
    const store = useQueueStore(); await store.load('account-1')
    expect(queueApi.list).toHaveBeenCalledWith('account-1', '', 1); expect(store.records).toEqual([record]); expect(store.agents).toHaveLength(1)
  })
  it('creates a queue using public agent references', async () => {
    vi.mocked(queueApi.options).mockResolvedValue({ agents: [], media: [] }); vi.mocked(queueApi.create).mockResolvedValue(record)
    vi.mocked(queueApi.list).mockResolvedValue({ data: [record], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 } }); vi.mocked(queueApi.agents).mockResolvedValue([])
    const store = useQueueStore(); await store.prepare('account-1')
    expect(await store.save('account-1', input)).toBe(true); expect(queueApi.create).toHaveBeenCalledWith('account-1', input)
  })
})
