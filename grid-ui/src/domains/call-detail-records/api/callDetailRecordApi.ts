import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  CallDetailRecord,
  CallDetailRecordFilters,
  SyncRun,
  SyncState,
} from '../types/callDetailRecord'

export type CallDetailRecordPage = {
  data: CallDetailRecord[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
    import_window_days: number
  }
}

export const callDetailRecordApi = {
  async list(
    accountId: string,
    filters: CallDetailRecordFilters,
    page = 1,
  ): Promise<CallDetailRecordPage> {
    const response = await http.get<CallDetailRecordPage>(
      `/api/v1/accounts/${accountId}/call-detail-records`,
      {
        params: {
          search: filters.search || undefined,
          direction: filters.direction || undefined,
          outcome: filters.outcome || undefined,
          hangup_cause: filters.hangup_cause || undefined,
          started_from: filters.started_from || undefined,
          started_to: filters.started_to || undefined,
          duration_min: filters.duration_min || undefined,
          duration_max: filters.duration_max || undefined,
          page,
          per_page: 25,
        },
      },
    )

    return response.data
  },
  async detail(accountId: string, recordId: string): Promise<CallDetailRecord> {
    const response = await http.get<ApiResponse<CallDetailRecord>>(
      `/api/v1/accounts/${accountId}/call-detail-records/${recordId}`,
    )

    return unwrapApiData(response)
  },
  async startSync(accountId: string): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/call-detail-records`,
    )

    return unwrapApiData(response)
  },
  async syncStatus(accountId: string, runId: string): Promise<SyncRun> {
    const response = await http.get<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/call-detail-records/${runId}`,
    )

    return unwrapApiData(response)
  },
}
