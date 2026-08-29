import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Agent, AgentStatus, AgentStatusInput, Queue, QueueInput, QueueOptions, QueueSyncRun } from '../types/queue'

export const queueApi = {
  async list(accountId: string, search = '', page = 1): Promise<PaginatedResponse<Queue>> {
    return (await http.get<PaginatedResponse<Queue>>(`/api/v1/accounts/${accountId}/queues`, { params: { search: search || undefined, page } })).data
  },
  async detail(accountId: string, id: string): Promise<Queue> {
    return unwrapApiData(await http.get<ApiResponse<Queue>>(`/api/v1/accounts/${accountId}/queues/${id}`))
  },
  async options(accountId: string): Promise<QueueOptions> {
    return unwrapApiData(await http.get<ApiResponse<QueueOptions>>(`/api/v1/accounts/${accountId}/queues/options`))
  },
  async create(accountId: string, input: QueueInput): Promise<Queue> {
    return unwrapApiData(await http.post<ApiResponse<Queue>>(`/api/v1/accounts/${accountId}/queues`, input))
  },
  async update(accountId: string, id: string, input: QueueInput): Promise<Queue> {
    const { max_priority: _createOnlyMaximumPriority, ...updateInput } = input

    return unwrapApiData(await http.put<ApiResponse<Queue>>(`/api/v1/accounts/${accountId}/queues/${id}`, updateInput))
  },
  async remove(accountId: string, id: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/queues/${id}`)
  },
  async agents(accountId: string): Promise<Agent[]> {
    return unwrapApiData(await http.get<ApiResponse<Agent[]>>(`/api/v1/accounts/${accountId}/agents`))
  },
  async agentStatus(accountId: string, id: string): Promise<AgentStatus> {
    return unwrapApiData(await http.get<ApiResponse<AgentStatus>>(`/api/v1/accounts/${accountId}/agents/${id}/status`))
  },
  async updateAgentStatus(accountId: string, id: string, input: AgentStatusInput): Promise<void> {
    await http.post(`/api/v1/accounts/${accountId}/agents/${id}/status`, input)
  },
  async startSync(accountId: string): Promise<QueueSyncRun> {
    return unwrapApiData(await http.post<ApiResponse<QueueSyncRun>>(`/api/v1/accounts/${accountId}/sync/queues`))
  },
  async syncStatus(accountId: string, runId: string): Promise<QueueSyncRun> {
    return unwrapApiData(await http.get<ApiResponse<QueueSyncRun>>(`/api/v1/accounts/${accountId}/sync/queues/${runId}`))
  },
}
