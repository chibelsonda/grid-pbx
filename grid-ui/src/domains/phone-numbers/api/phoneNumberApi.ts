import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { PhoneNumber, PhoneNumberFilters, SyncRun, SyncState } from '../types/phoneNumber'

export type PhoneNumberPage = {
  data: PhoneNumber[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
  }
}

export const phoneNumberApi = {
  async list(accountId: string, filters: PhoneNumberFilters, page = 1): Promise<PhoneNumberPage> {
    const response = await http.get<PhoneNumberPage>(
      `/api/v1/accounts/${accountId}/phone-numbers`,
      {
        params: {
          search: filters.search || undefined,
          state: filters.state || undefined,
          assignment: filters.assignment || undefined,
          feature: filters.feature || undefined,
          page,
          per_page: 25,
        },
      },
    )

    return response.data
  },
  async detail(accountId: string, phoneNumberId: string): Promise<PhoneNumber> {
    const response = await http.get<ApiResponse<PhoneNumber>>(
      `/api/v1/accounts/${accountId}/phone-numbers/${phoneNumberId}`,
    )

    return unwrapApiData(response)
  },
  async startSync(accountId: string, globalNotification = true): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/phone-numbers`,
      undefined,
      { globalNotification },
    )

    return unwrapApiData(response)
  },
  async syncStatus(accountId: string, runId: string): Promise<SyncRun> {
    const response = await http.get<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/phone-numbers/${runId}`,
    )

    return unwrapApiData(response)
  },
}
