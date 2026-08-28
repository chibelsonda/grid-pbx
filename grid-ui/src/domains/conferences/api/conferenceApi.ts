import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Conference, ConferenceInput, ConferenceOptions, ConferenceSyncRun } from '../types/conference'

export const conferenceApi = {
  async list(accountId: string, search = '', status = '', page = 1): Promise<PaginatedResponse<Conference>> {
    return (await http.get<PaginatedResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences`, { params: { search: search || undefined, status: status || undefined, page } })).data
  },
  async detail(accountId: string, id: string): Promise<Conference> {
    return unwrapApiData(await http.get<ApiResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences/${id}`))
  },
  async options(accountId: string): Promise<ConferenceOptions> {
    return unwrapApiData(await http.get<ApiResponse<ConferenceOptions>>(`/api/v1/accounts/${accountId}/conferences/options`))
  },
  async create(accountId: string, input: ConferenceInput): Promise<Conference> {
    return unwrapApiData(await http.post<ApiResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences`, input))
  },
  async update(accountId: string, id: string, input: ConferenceInput): Promise<Conference> {
    return unwrapApiData(await http.put<ApiResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences/${id}`, input))
  },
  async remove(accountId: string, id: string): Promise<void> { await http.delete(`/api/v1/accounts/${accountId}/conferences/${id}`) },
  async startSync(accountId: string): Promise<ConferenceSyncRun> {
    return unwrapApiData(await http.post<ApiResponse<ConferenceSyncRun>>(`/api/v1/accounts/${accountId}/sync/conferences`))
  },
  async syncStatus(accountId: string, runId: string): Promise<ConferenceSyncRun> {
    return unwrapApiData(await http.get<ApiResponse<ConferenceSyncRun>>(`/api/v1/accounts/${accountId}/sync/conferences/${runId}`))
  },
}
