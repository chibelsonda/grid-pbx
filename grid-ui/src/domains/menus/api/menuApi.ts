import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { Menu, MenuInput, MenuOptions, MenuSyncRun } from '../types/menu'

export const menuApi = {
  async list(accountId: string, search = '', page = 1): Promise<PaginatedResponse<Menu>> {
    return (
      await http.get<PaginatedResponse<Menu>>(`/api/v1/accounts/${accountId}/menus`, {
        params: { search: search || undefined, page },
      })
    ).data
  },
  async detail(accountId: string, id: string): Promise<Menu> {
    return unwrapApiData(
      await http.get<ApiResponse<Menu>>(`/api/v1/accounts/${accountId}/menus/${id}`),
    )
  },
  async options(accountId: string): Promise<MenuOptions> {
    return unwrapApiData(
      await http.get<ApiResponse<MenuOptions>>(`/api/v1/accounts/${accountId}/menus/options`),
    )
  },
  async create(accountId: string, input: MenuInput): Promise<Menu> {
    return unwrapApiData(
      await http.post<ApiResponse<Menu>>(`/api/v1/accounts/${accountId}/menus`, input),
    )
  },
  async update(accountId: string, id: string, input: MenuInput): Promise<Menu> {
    return unwrapApiData(
      await http.put<ApiResponse<Menu>>(`/api/v1/accounts/${accountId}/menus/${id}`, input),
    )
  },
  async remove(accountId: string, id: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/menus/${id}`)
  },
  async startSync(accountId: string): Promise<MenuSyncRun> {
    return unwrapApiData(
      await http.post<ApiResponse<MenuSyncRun>>(`/api/v1/accounts/${accountId}/sync/menus`),
    )
  },
  async syncStatus(accountId: string, runId: string): Promise<MenuSyncRun> {
    return unwrapApiData(
      await http.get<ApiResponse<MenuSyncRun>>(`/api/v1/accounts/${accountId}/sync/menus/${runId}`),
    )
  },
}
