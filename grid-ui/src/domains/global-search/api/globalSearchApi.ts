import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import { globalSearchResponseSchema } from '../schemas/globalSearchSchema'
import type { GlobalSearchResponse, GlobalSearchType } from '../types/globalSearch'

export const globalSearchApi = {
  async search(
    accountId: string,
    query: string,
    types: GlobalSearchType[] = [],
    signal?: AbortSignal,
  ): Promise<GlobalSearchResponse> {
    const response = await http.get<ApiResponse<unknown>>(`/api/v1/accounts/${accountId}/search`, {
      params: { q: query, types: types.length > 0 ? types : undefined },
      signal,
    })

    return globalSearchResponseSchema.parse(unwrapApiData(response))
  },
}
