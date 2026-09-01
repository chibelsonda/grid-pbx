import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  CallflowIntegrationProfile,
  CallflowIntegrationProfileInput,
  CallflowIntegrationProfileMetadataInput,
} from '../types/callflowIntegrationProfile'

function endpoint(accountId: string, profileId?: string): string {
  const base = `/api/v1/accounts/${accountId}/callflow-integration-profiles`

  return profileId ? `${base}/${profileId}` : base
}

export const callflowIntegrationProfileApi = {
  async list(accountId: string): Promise<CallflowIntegrationProfile[]> {
    const response = await http.get<ApiResponse<CallflowIntegrationProfile[]>>(endpoint(accountId))

    return unwrapApiData(response)
  },

  async create(
    accountId: string,
    input: CallflowIntegrationProfileInput,
  ): Promise<CallflowIntegrationProfile> {
    const response = await http.post<ApiResponse<CallflowIntegrationProfile>>(
      endpoint(accountId),
      input,
    )

    return unwrapApiData(response)
  },

  async update(
    accountId: string,
    profileId: string,
    input:
      | CallflowIntegrationProfileMetadataInput
      | CallflowIntegrationProfileInput,
  ): Promise<CallflowIntegrationProfile> {
    const response = await http.put<ApiResponse<CallflowIntegrationProfile>>(
      endpoint(accountId, profileId),
      input,
    )

    return unwrapApiData(response)
  },

  async remove(accountId: string, profileId: string): Promise<void> {
    await http.delete(endpoint(accountId, profileId))
  },
}
