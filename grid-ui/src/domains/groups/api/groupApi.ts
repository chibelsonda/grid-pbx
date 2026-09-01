import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Group, GroupInput, GroupOptions, GroupSyncRun } from '../types/group'

export const groupApi = {
  async list(accountId: string, search = '', page = 1): Promise<PaginatedResponse<Group>> {
    return (
      await http.get<PaginatedResponse<Group>>(`/api/v1/accounts/${accountId}/groups`, {
        params: { search: search || undefined, page },
      })
    ).data
  },
  async detail(accountId: string, id: string): Promise<Group> {
    return unwrapApiData(
      await http.get<ApiResponse<Group>>(`/api/v1/accounts/${accountId}/groups/${id}`),
    )
  },
  async options(accountId: string): Promise<GroupOptions> {
    return unwrapApiData(
      await http.get<ApiResponse<GroupOptions>>(`/api/v1/accounts/${accountId}/groups/options`),
    )
  },
  async create(accountId: string, input: GroupInput): Promise<Group> {
    return unwrapApiData(
      await http.post<ApiResponse<Group>>(`/api/v1/accounts/${accountId}/groups`, input),
    )
  },
  async update(accountId: string, id: string, input: GroupInput): Promise<Group> {
    return unwrapApiData(
      await http.put<ApiResponse<Group>>(`/api/v1/accounts/${accountId}/groups/${id}`, input),
    )
  },
  async remove(accountId: string, id: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/groups/${id}`)
  },
  async startSync(accountId: string): Promise<GroupSyncRun> {
    return unwrapApiData(
      await http.post<ApiResponse<GroupSyncRun>>(`/api/v1/accounts/${accountId}/sync/groups`),
    )
  },
  async syncStatus(accountId: string, runId: string): Promise<GroupSyncRun> {
    return unwrapApiData(
      await http.get<ApiResponse<GroupSyncRun>>(
        `/api/v1/accounts/${accountId}/sync/groups/${runId}`,
      ),
    )
  },
}
