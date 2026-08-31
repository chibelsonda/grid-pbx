import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  Callflow,
  CallflowCreateInput,
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
  async startProjectionSync(accountId: string): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/extensions`,
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
