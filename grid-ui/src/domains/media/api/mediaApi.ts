import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type {
  Media,
  MediaCreate,
  MediaFilters,
  MediaUpdate,
  SyncRun,
  SyncState,
} from '../types/media'

export type MediaPage = {
  data: Media[]
  links: { prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: SyncState
  }
}

function mediaForm(input: MediaCreate): FormData {
  const form = new FormData()
  form.append('name', input.name)
  if (input.description) form.append('description', input.description)
  if (input.language) form.append('language', input.language)
  form.append('streamable', input.streamable ? '1' : '0')
  form.append('audio', input.audio)
  return form
}

export const mediaApi = {
  async list(accountId: string, filters: MediaFilters, page = 1, perPage = 25): Promise<MediaPage> {
    const response = await http.get<MediaPage>(`/api/v1/accounts/${accountId}/media`, {
      params: {
        search: filters.search || undefined,
        media_source: filters.media_source || undefined,
        page,
        per_page: perPage,
      },
    })
    return response.data
  },
  async detail(accountId: string, mediaId: string): Promise<Media> {
    const response = await http.get<ApiResponse<Media>>(
      `/api/v1/accounts/${accountId}/media/${mediaId}`,
    )
    return unwrapApiData(response)
  },
  async create(accountId: string, input: MediaCreate): Promise<Media> {
    const response = await http.post<ApiResponse<Media>>(
      `/api/v1/accounts/${accountId}/media`,
      mediaForm(input),
    )
    return unwrapApiData(response)
  },
  async update(accountId: string, mediaId: string, input: MediaUpdate): Promise<Media> {
    const response = await http.put<ApiResponse<Media>>(
      `/api/v1/accounts/${accountId}/media/${mediaId}`,
      input,
    )
    return unwrapApiData(response)
  },
  async replaceAudio(accountId: string, mediaId: string, audio: File): Promise<Media> {
    const form = new FormData()
    form.append('audio', audio)
    const response = await http.post<ApiResponse<Media>>(
      `/api/v1/accounts/${accountId}/media/${mediaId}/audio`,
      form,
    )
    return unwrapApiData(response)
  },
  async remove(accountId: string, mediaId: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/media/${mediaId}`)
  },
  async updateMusicOnHold(accountId: string, mediaId: string | null): Promise<Media | null> {
    const response = await http.put<ApiResponse<{ media: Media | null }>>(
      `/api/v1/accounts/${accountId}/media/music-on-hold`,
      { media_id: mediaId },
    )
    return unwrapApiData(response).media
  },
  async audio(accountId: string, mediaId: string): Promise<Blob> {
    const response = await http.get(`/api/v1/accounts/${accountId}/media/${mediaId}/audio`, {
      responseType: 'blob',
    })
    return response.data
  },
  async startSync(accountId: string): Promise<SyncRun> {
    const response = await http.post<ApiResponse<SyncRun>>(`/api/v1/accounts/${accountId}/sync/media`)
    return unwrapApiData(response)
  },
  async syncStatus(accountId: string, runId: string): Promise<SyncRun> {
    const response = await http.get<ApiResponse<SyncRun>>(
      `/api/v1/accounts/${accountId}/sync/media/${runId}`,
    )
    return unwrapApiData(response)
  },
}
