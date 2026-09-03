import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  Callflow,
  CallflowCreateInput,
  CallflowEntryPointsUpdate,
  CallflowEditor,
  CallflowFilters,
  CallflowUpdate,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeDeleteInput,
  CallflowTreeNodeUpdateInput,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
  CallflowExtensionAvailability,
  CallflowExtensionDirectoryEntry,
  SyncRun,
  SyncState,
} from '../types/callRouting'

export type CallflowPage = {
  data: Callflow[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
  }
}

export type CallflowExtensionDirectory = {
  entries: CallflowExtensionDirectoryEntry[]
  suggested_extension: string | null
}

export const callflowApi = {
  async list(accountId: string, filters: CallflowFilters, page = 1): Promise<CallflowPage> {
    const response = await http.get<CallflowPage>(`/api/v1/accounts/${accountId}/callflows`, {
      params: {
        search: filters.search || undefined,
        type: filters.type || undefined,
        module: filters.module || undefined,
        page,
        per_page: 25,
      },
    })

    return response.data
  },
  async detail(accountId: string, callflowId: string): Promise<Callflow> {
    const response = await http.get<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}`,
    )

    return unwrapApiData(response)
  },
  async editor(accountId: string, callflowId: string): Promise<CallflowEditor> {
    const response = await http.get<ApiResponse<CallflowEditor>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/editor`,
    )

    return unwrapApiData(response)
  },
  async createEditor(accountId: string): Promise<CallflowEditor> {
    const response = await http.get<ApiResponse<CallflowEditor>>(
      `/api/v1/accounts/${accountId}/callflows/editor`,
    )

    return unwrapApiData(response)
  },
  async extensionDirectory(
    accountId: string,
    search = '',
    callflowId: string | null = null,
  ): Promise<CallflowExtensionDirectory> {
    const response = await http.get<ApiResponse<CallflowExtensionDirectory>>(
      `/api/v1/accounts/${accountId}/callflows/extension-directory`,
      { params: { search: search || undefined, callflow_id: callflowId || undefined } },
    )

    return unwrapApiData(response)
  },
  async extensionAvailability(
    accountId: string,
    number: string,
    callflowId: string | null = null,
  ): Promise<CallflowExtensionAvailability> {
    const response = await http.get<ApiResponse<CallflowExtensionAvailability>>(
      `/api/v1/accounts/${accountId}/callflows/extension-availability`,
      { params: { number, callflow_id: callflowId || undefined } },
    )

    return unwrapApiData(response)
  },
  async create(accountId: string, input: CallflowCreateInput): Promise<Callflow> {
    const response = await http.post<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows`,
      input,
    )

    return unwrapApiData(response)
  },
  async update(accountId: string, callflowId: string, input: CallflowUpdate): Promise<Callflow> {
    const response = await http.put<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}`,
      input,
    )

    return unwrapApiData(response)
  },
  async updateEntryPoints(
    accountId: string,
    callflowId: string,
    input: CallflowEntryPointsUpdate,
  ): Promise<Callflow> {
    const response = await http.patch<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/entry-points`,
      input,
    )

    return unwrapApiData(response)
  },
  async moveTreeNode(
    accountId: string,
    callflowId: string,
    input: CallflowTreeMoveInput,
  ): Promise<Callflow> {
    const response = await http.patch<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree`,
      input,
    )

    return unwrapApiData(response)
  },
  async createTreeNode(
    accountId: string,
    callflowId: string,
    input: CallflowTreeNodeCreateInput,
  ): Promise<Callflow> {
    const response = await http.post<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree/nodes`,
      input,
    )

    return unwrapApiData(response)
  },
  async reorderTreeNodes(
    accountId: string,
    callflowId: string,
    input: CallflowTreeReorderInput,
  ): Promise<Callflow> {
    const response = await http.patch<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree/order`,
      input,
    )

    return unwrapApiData(response)
  },
  async updateTreeNode(
    accountId: string,
    callflowId: string,
    input: CallflowTreeNodeUpdateInput,
  ): Promise<Callflow> {
    const response = await http.patch<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree/nodes`,
      input,
    )

    return unwrapApiData(response)
  },
  async deleteTreeNode(
    accountId: string,
    callflowId: string,
    input: CallflowTreeNodeDeleteInput,
  ): Promise<Callflow> {
    const response = await http.delete<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree/nodes`,
      { data: input },
    )

    return unwrapApiData(response)
  },
  async createInlineTreeNode(
    accountId: string,
    callflowId: string,
    input: CallflowInlineNodeCreateInput,
  ): Promise<Callflow> {
    const response = await http.post<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree/inline-nodes`,
      input,
    )

    return unwrapApiData(response)
  },
  async updateInlineTreeNode(
    accountId: string,
    callflowId: string,
    input: CallflowInlineNodeUpdateInput,
  ): Promise<Callflow> {
    const response = await http.patch<ApiResponse<Callflow>>(
      `/api/v1/accounts/${accountId}/callflows/${callflowId}/tree/inline-nodes`,
      input,
    )

    return unwrapApiData(response)
  },
  async delete(accountId: string, callflowId: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/callflows/${callflowId}`)
  },
  async startProjectionSync(accountId: string, globalNotification = true): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/extensions`,
      undefined,
      { globalNotification },
    )

    return unwrapApiData(response)
  },
  async syncStatus(accountId: string, runId: string): Promise<SyncRun> {
    const response = await http.get<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/extensions/${runId}`,
    )

    return unwrapApiData(response)
  },
}
