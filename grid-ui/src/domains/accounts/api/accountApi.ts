import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  Account,
  AccountDetail,
  AccountSettingsInput,
  AccountSettingsOptions,
  OrganizationBrandingResult,
} from '../types/account'

export const accountApi = {
  async list(): Promise<Account[]> {
    const response = await http.get<ApiResponse<Account[]>>('/api/v1/accounts')

    return unwrapApiData(response)
  },
  async detail(accountId: string): Promise<AccountDetail> {
    const response = await http.get<ApiResponse<AccountDetail>>(`/api/v1/accounts/${accountId}`)

    return unwrapApiData(response)
  },
  async settingsOptions(accountId: string): Promise<AccountSettingsOptions> {
    const response = await http.get<ApiResponse<AccountSettingsOptions>>(
      `/api/v1/accounts/${accountId}/settings-options`,
    )

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
  async organizationLogo(accountId: string): Promise<Blob> {
    const response = await http.get<Blob>(`/api/v1/accounts/${accountId}/organization-logo`, {
      responseType: 'blob',
    })

    return response.data
  },
  async uploadOrganizationLogo(accountId: string, logo: File): Promise<OrganizationBrandingResult> {
    const form = new FormData()
    form.append('logo', logo)
    const response = await http.post<ApiResponse<OrganizationBrandingResult>>(
      `/api/v1/accounts/${accountId}/organization-logo`,
      form,
    )

    return unwrapApiData(response)
  },
  async removeOrganizationLogo(accountId: string): Promise<OrganizationBrandingResult> {
    const response = await http.delete<ApiResponse<OrganizationBrandingResult>>(
      `/api/v1/accounts/${accountId}/organization-logo`,
    )

    return unwrapApiData(response)
  },
}
