import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import { operationalStatusSchema, type OperationalStatus } from '../schemas/operationalStatusSchema'

export const operationalStatusApi = {
  async get(accountId: string): Promise<OperationalStatus> {
    return operationalStatusSchema.parse(
      unwrapApiData(
        await http.get<ApiResponse<OperationalStatus>>(
          `/api/v1/accounts/${accountId}/operational-status`,
        ),
      ),
    )
  },
}
