import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  Extension,
  ExtensionCreate,
  ExtensionDeletionPreview,
  ExtensionDetail,
  ExtensionFormOptions,
  ExtensionRecoveryOperation,
  ExtensionUpdate,
  SyncRun,
  SyncState,
} from '../types/extension'

export type ExtensionPage = {
  data: Extension[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
  }
}

export const extensionApi = {
  async options(accountId: string): Promise<ExtensionFormOptions> {
    const response = await http.get<ApiResponse<ExtensionFormOptions>>(
      `/api/v1/accounts/${accountId}/extensions/options`,
    )

    return unwrapApiData(response)
  },
  async list(accountId: string, search = '', page = 1): Promise<ExtensionPage> {
    const response = await http.get<ExtensionPage>(`/api/v1/accounts/${accountId}/extensions`, {
      params: { search: search || undefined, page, per_page: 25 },
    })

    return response.data
  },
  async detail(accountId: string, extensionId: string): Promise<ExtensionDetail> {
    const response = await http.get<ApiResponse<ExtensionDetail>>(
      `/api/v1/accounts/${accountId}/extensions/${extensionId}`,
    )

    return unwrapApiData(response)
  },
  async create(accountId: string, input: ExtensionCreate): Promise<ExtensionDetail> {
    const response = await http.post<ApiResponse<ExtensionDetail>>(
      `/api/v1/accounts/${accountId}/extensions`,
      input,
    )

    return unwrapApiData(response)
  },
  async update(
    accountId: string,
    extensionId: string,
    input: ExtensionUpdate,
  ): Promise<ExtensionDetail> {
    const response = await http.put<ApiResponse<ExtensionDetail>>(
      `/api/v1/accounts/${accountId}/extensions/${extensionId}`,
      input,
    )

    return unwrapApiData(response)
  },
  async deletionPreview(accountId: string, extensionId: string): Promise<ExtensionDeletionPreview> {
    const response = await http.get<ApiResponse<ExtensionDeletionPreview>>(
      `/api/v1/accounts/${accountId}/extensions/${extensionId}/deletion-preview`,
    )

    return unwrapApiData(response)
  },
  async remove(accountId: string, extensionId: string, confirmation: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/extensions/${extensionId}`, {
      data: { confirmation },
    })
  },
  async recoveryQueue(accountId: string): Promise<ExtensionRecoveryOperation[]> {
    const response = await http.get<ApiResponse<ExtensionRecoveryOperation[]>>(
      `/api/v1/accounts/${accountId}/extension-recovery`,
    )

    return unwrapApiData(response)
  },
  async recover(
    accountId: string,
    operationId: string,
    confirmation: string | null = null,
  ): Promise<ExtensionRecoveryOperation> {
    const response = await http.post<ApiResponse<ExtensionRecoveryOperation>>(
      `/api/v1/accounts/${accountId}/extension-recovery/${operationId}`,
      { confirmation },
    )

    return unwrapApiData(response)
  },
  async startSync(accountId: string): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/extensions`,
    )

    return unwrapApiData(response)
  },
  async syncRun(accountId: string, runId: string): Promise<SyncRun> {
    const response = await http.get<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/extensions/${runId}`,
    )

    return unwrapApiData(response)
  },
}
