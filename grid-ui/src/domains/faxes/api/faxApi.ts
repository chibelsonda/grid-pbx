import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Fax, FaxBox, FaxBoxInput, FaxBoxOptions, FaxSyncRun } from '../types/fax'

export const faxApi = {
  async boxes(accountId: string): Promise<PaginatedResponse<FaxBox>> { return (await http.get<PaginatedResponse<FaxBox>>(`/api/v1/accounts/${accountId}/fax-boxes`, { params: { per_page: 100 } })).data },
  async box(accountId: string, id: string): Promise<FaxBox> { return unwrapApiData(await http.get<ApiResponse<FaxBox>>(`/api/v1/accounts/${accountId}/fax-boxes/${id}`)) },
  async options(accountId: string): Promise<FaxBoxOptions> { return unwrapApiData(await http.get<ApiResponse<FaxBoxOptions>>(`/api/v1/accounts/${accountId}/fax-boxes/options`)) },
  async createBox(accountId: string, input: FaxBoxInput): Promise<FaxBox> { return unwrapApiData(await http.post<ApiResponse<FaxBox>>(`/api/v1/accounts/${accountId}/fax-boxes`, input)) },
  async updateBox(accountId: string, id: string, input: FaxBoxInput): Promise<FaxBox> { return unwrapApiData(await http.put<ApiResponse<FaxBox>>(`/api/v1/accounts/${accountId}/fax-boxes/${id}`, input)) },
  async removeBox(accountId: string, id: string): Promise<void> { await http.delete(`/api/v1/accounts/${accountId}/fax-boxes/${id}`) },
  async messages(accountId: string, search = '', folder = '', faxBoxId = '', page = 1): Promise<PaginatedResponse<Fax>> { return (await http.get<PaginatedResponse<Fax>>(`/api/v1/accounts/${accountId}/faxes`, { params: { search: search || undefined, folder: folder || undefined, fax_box_id: faxBoxId || undefined, page } })).data },
  async message(accountId: string, id: string): Promise<Fax> { return unwrapApiData(await http.get<ApiResponse<Fax>>(`/api/v1/accounts/${accountId}/faxes/${id}`)) },
  async document(accountId: string, id: string): Promise<Blob> { return (await http.get(`/api/v1/accounts/${accountId}/faxes/${id}/document`, { params: { download: 1 }, responseType: 'blob' })).data as Blob },
  async startSync(accountId: string): Promise<FaxSyncRun> { return unwrapApiData(await http.post<ApiResponse<FaxSyncRun>>(`/api/v1/accounts/${accountId}/sync/faxes`)) },
  async syncStatus(accountId: string, runId: string): Promise<FaxSyncRun> { return unwrapApiData(await http.get<ApiResponse<FaxSyncRun>>(`/api/v1/accounts/${accountId}/sync/faxes/${runId}`)) },
}
