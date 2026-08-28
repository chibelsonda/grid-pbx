import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { Recording, RecordingFilters, RecordingSyncRun, RecordingSyncState } from '../types/recording'

export type RecordingPage = { data: Recording[]; links: { first: string | null; last: string | null; prev: string | null; next: string | null }; meta: { current_page: number; last_page: number; per_page: number; total: number; sync: RecordingSyncState; import_window_days: number } }
export const recordingApi = {
  async list(accountId: string, filters: RecordingFilters, page = 1): Promise<RecordingPage> { return (await http.get<RecordingPage>(`/api/v1/accounts/${accountId}/recordings`, { params: { search: filters.search || undefined, direction: filters.direction || undefined, started_from: filters.started_from || undefined, started_to: filters.started_to || undefined, duration_min: filters.duration_min || undefined, duration_max: filters.duration_max || undefined, has_audio: filters.has_audio || undefined, page } })).data },
  async detail(accountId: string, id: string): Promise<Recording> { return unwrapApiData(await http.get<ApiResponse<Recording>>(`/api/v1/accounts/${accountId}/recordings/${id}`)) },
  async audio(accountId: string, id: string, download = false): Promise<Blob> { return (await http.get(`/api/v1/accounts/${accountId}/recordings/${id}/audio`, { params: { download: download ? 1 : undefined }, responseType: 'blob' })).data },
  async startSync(accountId: string): Promise<RecordingSyncRun> { return unwrapApiData(await http.post<ApiResponse<RecordingSyncRun>>(`/api/v1/accounts/${accountId}/sync/recordings`)) },
  async syncStatus(accountId: string, id: string): Promise<RecordingSyncRun> { return unwrapApiData(await http.get<ApiResponse<RecordingSyncRun>>(`/api/v1/accounts/${accountId}/sync/recordings/${id}`)) },
}
