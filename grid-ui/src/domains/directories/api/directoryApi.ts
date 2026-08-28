import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Directory, DirectoryInput, DirectoryOptions, DirectorySyncRun } from '../types/directory'

export const directoryApi = {
  async list(accountId: string, search = '', page = 1): Promise<PaginatedResponse<Directory>> {
    const response = await http.get<PaginatedResponse<Directory>>(`/api/v1/accounts/${accountId}/directories`, {
      params: { search: search || undefined, page },
    })
    return response.data
  },
  async detail(accountId: string, id: string): Promise<Directory> {
    return unwrapApiData(await http.get<ApiResponse<Directory>>(`/api/v1/accounts/${accountId}/directories/${id}`))
  },
  async options(accountId: string): Promise<DirectoryOptions> {
    return unwrapApiData(await http.get<ApiResponse<DirectoryOptions>>(`/api/v1/accounts/${accountId}/directories/options`))
  },
  async create(accountId: string, input: DirectoryInput): Promise<Directory> {
    return unwrapApiData(await http.post<ApiResponse<Directory>>(`/api/v1/accounts/${accountId}/directories`, input))
  },
  async update(accountId: string, id: string, input: DirectoryInput): Promise<Directory> {
    return unwrapApiData(await http.put<ApiResponse<Directory>>(`/api/v1/accounts/${accountId}/directories/${id}`, input))
  },
  async remove(accountId: string, id: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/directories/${id}`)
  },
  async startSync(accountId: string): Promise<DirectorySyncRun> {
    return unwrapApiData(await http.post<ApiResponse<DirectorySyncRun>>(`/api/v1/accounts/${accountId}/sync/directories`))
  },
  async syncStatus(accountId: string, runId: string): Promise<DirectorySyncRun> {
    return unwrapApiData(await http.get<ApiResponse<DirectorySyncRun>>(`/api/v1/accounts/${accountId}/sync/directories/${runId}`))
  },
}
