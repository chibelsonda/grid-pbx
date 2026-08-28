import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { ServiceOverview, ServiceSyncRun } from '../types/service'

export const serviceApi = {
  async overview(accountId: string): Promise<ServiceOverview | null> {
    return unwrapApiData(
      await http.get<ApiResponse<ServiceOverview | null>>(`/api/v1/accounts/${accountId}/services`),
    )
  },
  async startSync(accountId: string): Promise<ServiceSyncRun> {
    return unwrapApiData(
      await http.post<ApiResponse<ServiceSyncRun>>(`/api/v1/accounts/${accountId}/sync/services`),
    )
  },
  async syncStatus(accountId: string, runId: string): Promise<ServiceSyncRun> {
    return unwrapApiData(
      await http.get<ApiResponse<ServiceSyncRun>>(
        `/api/v1/accounts/${accountId}/sync/services/${runId}`,
      ),
    )
  },
}
