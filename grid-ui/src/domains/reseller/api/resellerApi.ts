import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  AccountHierarchy,
  DescendantOnboardingCandidates,
  DescendantOnboardingInput,
  DescendantOnboardingResult,
  ResellerStatus,
} from '../types/reseller'

export const resellerApi = {
  async hierarchy(accountId: string): Promise<AccountHierarchy> {
    return unwrapApiData(
      await http.get<ApiResponse<AccountHierarchy>>(`/api/v1/accounts/${accountId}/hierarchy`),
    )
  },

  async status(accountId: string): Promise<ResellerStatus> {
    return unwrapApiData(
      await http.get<ApiResponse<ResellerStatus>>(`/api/v1/accounts/${accountId}/reseller`),
    )
  },

  async onboardingCandidates(accountId: string): Promise<DescendantOnboardingCandidates> {
    return unwrapApiData(
      await http.get<ApiResponse<DescendantOnboardingCandidates>>(
        `/api/v1/accounts/${accountId}/descendant-onboarding`,
      ),
    )
  },

  async onboardDescendant(
    accountId: string,
    input: DescendantOnboardingInput,
  ): Promise<DescendantOnboardingResult> {
    return unwrapApiData(
      await http.post<ApiResponse<DescendantOnboardingResult>>(
        `/api/v1/accounts/${accountId}/descendant-onboarding`,
        input,
      ),
    )
  },
}
