import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Blacklist, BlacklistInput, BlacklistSyncRun } from '../types/blacklist'

export const blacklistApi = {
  async list(accountId: string, search = '', page = 1): Promise<PaginatedResponse<Blacklist>> { return (await http.get<PaginatedResponse<Blacklist>>(`/api/v1/accounts/${accountId}/blacklists`, { params: { search: search || undefined, page } })).data },
  async detail(accountId: string, id: string): Promise<Blacklist> { return unwrapApiData(await http.get<ApiResponse<Blacklist>>(`/api/v1/accounts/${accountId}/blacklists/${id}`)) },
  async create(accountId: string, input: BlacklistInput): Promise<Blacklist> { return unwrapApiData(await http.post<ApiResponse<Blacklist>>(`/api/v1/accounts/${accountId}/blacklists`, input)) },
  async update(accountId: string, id: string, input: BlacklistInput): Promise<Blacklist> { return unwrapApiData(await http.put<ApiResponse<Blacklist>>(`/api/v1/accounts/${accountId}/blacklists/${id}`, input)) },
  async remove(accountId: string, id: string): Promise<void> { await http.delete(`/api/v1/accounts/${accountId}/blacklists/${id}`) },
  async startSync(accountId: string): Promise<BlacklistSyncRun> { return unwrapApiData(await http.post<ApiResponse<BlacklistSyncRun>>(`/api/v1/accounts/${accountId}/sync/blacklists`)) },
  async syncStatus(accountId: string, id: string): Promise<BlacklistSyncRun> { return unwrapApiData(await http.get<ApiResponse<BlacklistSyncRun>>(`/api/v1/accounts/${accountId}/sync/blacklists/${id}`)) },
}
