import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { Account, AccountDetail, AccountSettingsInput } from '../types/account'

export const accountApi = {
  async list(): Promise<Account[]> {
    const response = await http.get<ApiResponse<Account[]>>('/api/v1/accounts')

    return unwrapApiData(response)
  },
  async detail(accountId: string): Promise<AccountDetail> {
    const response = await http.get<ApiResponse<AccountDetail>>(`/api/v1/accounts/${accountId}`)

    return unwrapApiData(response)
  },
  async update(accountId: string, input: AccountSettingsInput): Promise<AccountDetail> {
    const response = await http.put<ApiResponse<AccountDetail>>(
      `/api/v1/accounts/${accountId}`,
      input,
    )

    return unwrapApiData(response)
  },
  async refresh(accountId: string): Promise<AccountDetail> {
    const response = await http.post<ApiResponse<AccountDetail>>(
      `/api/v1/accounts/${accountId}/sync`,
    )

    return unwrapApiData(response)
  },
  async updateStatus(
    accountId: string,
    enabled: boolean,
    confirmation: string,
  ): Promise<AccountDetail> {
    const response = await http.put<ApiResponse<AccountDetail>>(
      `/api/v1/accounts/${accountId}/status`,
      { enabled, confirmation },
    )

    return unwrapApiData(response)
  },
}
