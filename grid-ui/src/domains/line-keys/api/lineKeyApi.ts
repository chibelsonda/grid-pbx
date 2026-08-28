import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { LineKeyDevice, LineKeyInput, LineKeyPreview } from '../types/lineKey'

type LineKeyUpdateResponse = { device: LineKeyDevice }

export const lineKeyApi = {
  async list(accountId: string, search = ''): Promise<LineKeyDevice[]> {
    return unwrapApiData(
      await http.get<ApiResponse<LineKeyDevice[]>>(`/api/v1/accounts/${accountId}/line-keys`, {
        params: { search: search || undefined },
      }),
    )
  },
  async preview(accountId: string, deviceId: string): Promise<LineKeyPreview> {
    return unwrapApiData(
      await http.get<ApiResponse<LineKeyPreview>>(
        `/api/v1/accounts/${accountId}/devices/${deviceId}/line-keys/preview`,
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
