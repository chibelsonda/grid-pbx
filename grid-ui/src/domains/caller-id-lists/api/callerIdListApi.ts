import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { CallerIdList, CallerIdListInput, CallerIdListSyncRun } from '../types/callerIdList'

const resourcePath = (accountId: string) => `/api/v1/accounts/${accountId}/caller-id-lists`

export const callerIdListApi = {
  async list(accountId: string, search = '', page = 1): Promise<PaginatedResponse<CallerIdList>> {
    return (
      await http.get<PaginatedResponse<CallerIdList>>(resourcePath(accountId), {
        params: { search: search || undefined, page },
      })
    ).data
  },
  async detail(accountId: string, id: string): Promise<CallerIdList> {
    return unwrapApiData(
      await http.get<ApiResponse<CallerIdList>>(`${resourcePath(accountId)}/${id}`),
    )
  },
  async create(accountId: string, input: CallerIdListInput): Promise<CallerIdList> {
    return unwrapApiData(await http.post<ApiResponse<CallerIdList>>(resourcePath(accountId), input))
  },
  async update(accountId: string, id: string, input: CallerIdListInput): Promise<CallerIdList> {
    return unwrapApiData(
      await http.put<ApiResponse<CallerIdList>>(`${resourcePath(accountId)}/${id}`, input),
    )
  },
  async remove(accountId: string, id: string): Promise<void> {
    await http.delete(`${resourcePath(accountId)}/${id}`)
  },
  async startSync(accountId: string): Promise<CallerIdListSyncRun> {
    return unwrapApiData(
      await http.post<ApiResponse<CallerIdListSyncRun>>(
        `/api/v1/accounts/${accountId}/sync/caller-id-lists`,
      ),
    )
  },
  async syncStatus(accountId: string, id: string): Promise<CallerIdListSyncRun> {
    return unwrapApiData(
      await http.get<ApiResponse<CallerIdListSyncRun>>(
        `/api/v1/accounts/${accountId}/sync/caller-id-lists/${id}`,
      ),
    )
  },
}
