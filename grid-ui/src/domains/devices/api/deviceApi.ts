import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  Device,
  DeviceHotdeskMemberships,
  DeviceInput,
  DeviceOptions,
  DeviceProvisioningEnrollment,
  DeviceProvisioningEnrollmentMutation,
  SyncState,
} from '../types/device'

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

export type DeviceProvisioningCommand = 'sync' | 'reprovision'

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
  async syncProvisioning(
    accountId: string,
    deviceId: string,
    command: DeviceProvisioningCommand,
  ): Promise<{ message: string; command: DeviceProvisioningCommand }> {
    const response = await http.post<
      ApiResponse<{ message: string; command: DeviceProvisioningCommand }>
    >(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/provisioning-sync`,
      { command },
    )

    return unwrapApiData(response)
  },
  async provisioningEnrollment(
    accountId: string,
    deviceId: string,
  ): Promise<DeviceProvisioningEnrollment> {
    const response = await http.get<ApiResponse<DeviceProvisioningEnrollment>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/provisioning-enrollment`,
    )

    return unwrapApiData(response)
  },
  async enrollProvisioning(
    accountId: string,
    deviceId: string,
  ): Promise<DeviceProvisioningEnrollmentMutation> {
    const response = await http.post<ApiResponse<DeviceProvisioningEnrollmentMutation>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/provisioning-enrollment`,
      { confirmed: true },
    )

    return unwrapApiData(response)
  },
  async detachProvisioning(
    accountId: string,
    deviceId: string,
  ): Promise<DeviceProvisioningEnrollmentMutation> {
    const response = await http.delete<ApiResponse<DeviceProvisioningEnrollmentMutation>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/provisioning-enrollment`,
      { data: { confirmed: true } },
    )

    return unwrapApiData(response)
  },
  async options(accountId: string): Promise<DeviceOptions> {
    const response = await http.get<ApiResponse<DeviceOptions>>(
      `/api/v1/accounts/${accountId}/devices/options`,
    )

    return unwrapApiData(response)
  },
  async hotdeskUsers(accountId: string, deviceId: string): Promise<DeviceHotdeskMemberships> {
    const response = await http.get<ApiResponse<DeviceHotdeskMemberships>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/hotdesk-users`,
    )

    return unwrapApiData(response)
  },
  async signInHotdeskUser(
    accountId: string,
    deviceId: string,
    extensionId: string,
  ): Promise<DeviceHotdeskMemberships> {
    const response = await http.put<ApiResponse<DeviceHotdeskMemberships>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/hotdesk-users/${extensionId}`,
    )

    return unwrapApiData(response)
  },
  async signOutHotdeskUser(
    accountId: string,
    deviceId: string,
    extensionId: string,
  ): Promise<DeviceHotdeskMemberships> {
    const response = await http.delete<ApiResponse<DeviceHotdeskMemberships>>(
      `/api/v1/accounts/${accountId}/devices/${deviceId}/hotdesk-users/${extensionId}`,
    )

    return unwrapApiData(response)
  },
}
