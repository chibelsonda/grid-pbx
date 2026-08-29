import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  SyncState,
  VoicemailBox,
  VoicemailBoxInput,
  VoicemailMessage,
  VoicemailMessageBulkResult,
  VoicemailMessageFolder,
  VoicemailGreeting,
  VoicemailFormOptions,
} from '../types/voicemail'

export type VoicemailBoxPage = {
  data: VoicemailBox[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
  }
}

export type VoicemailMessagePage = {
  data: VoicemailMessage[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const voicemailApi = {
  async options(accountId: string): Promise<VoicemailFormOptions> {
    const response = await http.get<ApiResponse<VoicemailFormOptions>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/options`,
    )

    return unwrapApiData(response)
  },
  async list(accountId: string, search = '', page = 1): Promise<VoicemailBoxPage> {
    const response = await http.get<VoicemailBoxPage>(
      `/api/v1/accounts/${accountId}/voicemail-boxes`,
      { params: { search: search || undefined, page, per_page: 25 } },
    )

    return response.data
  },
  async detail(accountId: string, voicemailBoxId: string): Promise<VoicemailBox> {
    const response = await http.get<ApiResponse<VoicemailBox>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}`,
    )

    return unwrapApiData(response)
  },
  async create(accountId: string, input: VoicemailBoxInput): Promise<VoicemailBox> {
    const response = await http.post<ApiResponse<VoicemailBox>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes`,
      input,
    )

    return unwrapApiData(response)
  },
  async update(
    accountId: string,
    voicemailBoxId: string,
    input: VoicemailBoxInput,
  ): Promise<VoicemailBox> {
    const response = await http.put<ApiResponse<VoicemailBox>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}`,
      input,
    )

    return unwrapApiData(response)
  },
  async remove(accountId: string, voicemailBoxId: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}`)
  },
  async messages(
    accountId: string,
    voicemailBoxId: string,
    search = '',
    folder = '',
    page = 1,
  ): Promise<VoicemailMessagePage> {
    const response = await http.get<VoicemailMessagePage>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}/messages`,
      { params: { search: search || undefined, folder: folder || undefined, page, per_page: 25 } },
    )

    return response.data
  },
  async changeMessageFolder(
    accountId: string,
    voicemailBoxId: string,
    messageId: string,
    folder: VoicemailMessageFolder,
  ): Promise<VoicemailMessage> {
    const response = await http.patch<ApiResponse<VoicemailMessage>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}/messages/${messageId}`,
      { folder },
    )

    return unwrapApiData(response)
  },
  async bulkChangeMessageFolder(
    accountId: string,
    voicemailBoxId: string,
    messageIds: string[],
    folder: VoicemailMessageFolder,
  ): Promise<VoicemailMessageBulkResult> {
    const response = await http.patch<ApiResponse<VoicemailMessageBulkResult>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}/messages`,
      { message_ids: messageIds, folder },
    )

    return unwrapApiData(response)
  },
  async uploadGreeting(
    accountId: string,
    voicemailBoxId: string,
    name: string,
    audio: File,
  ): Promise<VoicemailGreeting> {
    const form = new FormData()
    if (name.trim()) form.append('name', name.trim())
    form.append('audio', audio)
    const response = await http.post<ApiResponse<VoicemailGreeting>>(
      `/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}/greeting`,
      form,
    )

    return unwrapApiData(response)
  },
  async removeGreeting(accountId: string, voicemailBoxId: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/voicemail-boxes/${voicemailBoxId}/greeting`)
  },
  greetingAudioUrl(accountId: string, voicemailBoxId: string): string {
    const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8081'
    return new URL(
      `/api/v1/accounts/${encodeURIComponent(accountId)}/voicemail-boxes/${encodeURIComponent(voicemailBoxId)}/greeting/audio`,
      baseUrl,
    ).toString()
  },
  audioUrl(accountId: string, voicemailBoxId: string, messageId: string, download = false): string {
    const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8081'
    const url = new URL(
      `/api/v1/accounts/${encodeURIComponent(accountId)}/voicemail-boxes/${encodeURIComponent(voicemailBoxId)}/messages/${encodeURIComponent(messageId)}/audio`,
      baseUrl,
    )

    if (download) url.searchParams.set('download', '1')

    return url.toString()
  },
}
