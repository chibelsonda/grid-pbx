import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { Device, DeviceInput, ExtensionOption, SyncState } from '../types/device'

export type DevicePage = {
  data: Device[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
  }
}

export const deviceApi = {
  async list(accountId: string, search = '', page = 1): Promise<DevicePage> {
    const response = await http.get<DevicePage>(`/api/v1/accounts/${accountId}/devices`, {
      params: { search: search || undefined, page, per_page: 25 },
    })

    return response.data
  },
  async detail(accountId: string, deviceId: string): Promise<Device> {
    const response = await http.get<ApiResponse<Device>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}`,
    )

    return unwrapApiData(response)
  },
  async create(accountId: string, device: DeviceInput): Promise<Device> {
    const response = await http.post<ApiResponse<Device>>(
      `/api/v1/accounts/${accountId}/devices`,
      device,
    )

    return unwrapApiData(response)
  },
  async update(accountId: string, deviceId: string, device: DeviceInput): Promise<Device> {
    const response = await http.put<ApiResponse<Device>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}`,
      device,
    )

    return unwrapApiData(response)
  },
  async remove(accountId: string, deviceId: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/devices/${deviceId}`)
  },
  async extensionOptions(accountId: string): Promise<ExtensionOption[]> {
    const response = await http.get<{ data: ExtensionOption[] }>(
      `/api/v1/accounts/${accountId}/extensions`,
      { params: { per_page: 100 } },
    )

    return unwrapApiData(response)
  },
}
