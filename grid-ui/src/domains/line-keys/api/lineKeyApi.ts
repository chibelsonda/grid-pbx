import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  LineKeyDevice,
  LineKeyInput,
  LineKeyPreview,
  LineKeySyncRun,
  LineKeySyncState,
} from '../types/lineKey'

type LineKeyUpdateResponse = { device: LineKeyDevice }
export type LineKeyListResponse = {
  data: LineKeyDevice[]
  meta: { sync: LineKeySyncState }
}

export const lineKeyApi = {
  async list(accountId: string, search = ''): Promise<LineKeyListResponse> {
    const response = await http.get<LineKeyListResponse>(
      `/api/v1/accounts/${accountId}/line-keys`,
      {
        params: { search: search || undefined },
      },
    )

    return response.data
  },
  async preview(accountId: string, deviceId: string): Promise<LineKeyPreview> {
    return unwrapApiData(
      await http.get<ApiResponse<LineKeyPreview>>(
        `/api/v1/accounts/${accountId}/devices/${deviceId}/line-keys/preview`,
      ),
    )
  },
  async startSync(accountId: string): Promise<LineKeySyncRun> {
    return unwrapApiData(
      await http.post<ApiResponse<LineKeySyncRun>>(`/api/v1/accounts/${accountId}/sync/extensions`),
    )
  },
  async syncStatus(accountId: string, runId: string): Promise<LineKeySyncRun> {
    return unwrapApiData(
      await http.get<ApiResponse<LineKeySyncRun>>(
        `/api/v1/accounts/${accountId}/sync/extensions/${runId}`,
      ),
    )
  },
  async update(
    accountId: string,
    deviceId: string,
    lineKeys: LineKeyInput[],
  ): Promise<LineKeyDevice> {
    const response = await http.put<ApiResponse<LineKeyUpdateResponse>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/line-keys`,
      { line_keys: lineKeys },
    )

    return unwrapApiData(response).device
  },
}
