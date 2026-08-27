import { http, type ApiResponse } from '@/shared/api/http'
import type { Extension, SyncRun, SyncState } from '../types/extension'

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
  async list(accountId: string, search = '', page = 1): Promise<ExtensionPage> {
    const response = await http.get<ExtensionPage>(`/api/v1/accounts/${accountId}/extensions`, {
      params: { search: search || undefined, page, per_page: 25 },
    })

    return response.data
  },
  async startSync(accountId: string): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(`/api/v1/accounts/${accountId}/sync/extensions`)

    return response.data.data
  },
  async syncRun(accountId: string, runId: string): Promise<SyncRun> {
    const response = await http.get<ApiResponse<SyncRun>>(`/api/v1/accounts/${accountId}/sync/extensions/${runId}`)

    return response.data.data
  },
}
