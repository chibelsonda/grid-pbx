import { http } from '@/shared/api/http'
import { featureCodePageSchema } from '../schemas/featureCodeSchema'
import type { FeatureCodePage } from '../types/featureCode'

export const featureCodeApi = {
  async list(accountId: string): Promise<FeatureCodePage> {
    const response = await http.get(`/api/v1/accounts/${accountId}/callflows`, {
      params: {
        type: 'feature_code',
        per_page: 100,
      },
    })

    return featureCodePageSchema.parse(response.data)
  },
}
