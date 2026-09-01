import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type {
  Conference,
  ConferenceBulkControlResult,
  ConferenceBulkParticipantAction,
  ConferenceControlAction,
  ConferenceControlResult,
  ConferenceInput,
  ConferenceOptions,
  ConferenceParticipant,
  ConferenceParticipantAction,
  ConferencePlaybackResult,
  ConferenceSyncRun,
} from '../types/conference'

export const conferenceApi = {
  async list(
    accountId: string,
    search = '',
    status = '',
    page = 1,
  ): Promise<PaginatedResponse<Conference>> {
    return (
      await http.get<PaginatedResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences`, {
        params: { search: search || undefined, status: status || undefined, page },
      })
    ).data
  },
  async detail(accountId: string, id: string): Promise<Conference> {
    return unwrapApiData(
      await http.get<ApiResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences/${id}`),
    )
  },
  async options(accountId: string): Promise<ConferenceOptions> {
    return unwrapApiData(
      await http.get<ApiResponse<ConferenceOptions>>(
        `/api/v1/accounts/${accountId}/conferences/options`,
      ),
    )
  },
  async create(accountId: string, input: ConferenceInput): Promise<Conference> {
    return unwrapApiData(
      await http.post<ApiResponse<Conference>>(`/api/v1/accounts/${accountId}/conferences`, input),
    )
  },
  async update(accountId: string, id: string, input: ConferenceInput): Promise<Conference> {
    return unwrapApiData(
      await http.put<ApiResponse<Conference>>(
        `/api/v1/accounts/${accountId}/conferences/${id}`,
        input,
      ),
    )
  },
  async remove(accountId: string, id: string): Promise<void> {
    await http.delete(`/api/v1/accounts/${accountId}/conferences/${id}`)
  },
  async control(
    accountId: string,
    id: string,
    action: ConferenceControlAction,
  ): Promise<ConferenceControlResult> {
    return unwrapApiData(
      await http.post<ApiResponse<ConferenceControlResult>>(
        `/api/v1/accounts/${accountId}/conferences/${id}/commands`,
        { action },
      ),
    )
  },
  async participants(accountId: string, id: string): Promise<ConferenceParticipant[]> {
    return unwrapApiData(
      await http.get<ApiResponse<ConferenceParticipant[]>>(
        `/api/v1/accounts/${accountId}/conferences/${id}/participants`,
      ),
    )
  },
  async controlParticipant(
    accountId: string,
    id: string,
    participantId: string,
    action: ConferenceParticipantAction,
  ): Promise<ConferenceControlResult> {
    return unwrapApiData(
      await http.post<ApiResponse<ConferenceControlResult>>(
        `/api/v1/accounts/${accountId}/conferences/${id}/participants/commands`,
        { participant_id: participantId, action },
      ),
    )
  },
  async controlParticipants(
    accountId: string,
    id: string,
    action: ConferenceBulkParticipantAction,
    expectedParticipantCount: number,
    expectedTargetCount: number,
  ): Promise<ConferenceBulkControlResult> {
    return unwrapApiData(
      await http.post<ApiResponse<ConferenceBulkControlResult>>(
        `/api/v1/accounts/${accountId}/conferences/${id}/participants/bulk-commands`,
        {
          action,
          expected_participant_count: expectedParticipantCount,
          expected_target_count: expectedTargetCount,
          confirmation: true,
        },
      ),
    )
  },
  async playMedia(
    accountId: string,
    id: string,
    mediaId: string,
    participantId: string | null,
  ): Promise<ConferencePlaybackResult> {
    return unwrapApiData(
      await http.post<ApiResponse<ConferencePlaybackResult>>(
        `/api/v1/accounts/${accountId}/conferences/${id}/playback`,
        { media_id: mediaId, participant_id: participantId, confirmation: true },
      ),
    )
  },
  async startSync(accountId: string): Promise<ConferenceSyncRun> {
    return unwrapApiData(
      await http.post<ApiResponse<ConferenceSyncRun>>(
        `/api/v1/accounts/${accountId}/sync/conferences`,
      ),
    )
  },
  async syncStatus(accountId: string, runId: string): Promise<ConferenceSyncRun> {
    return unwrapApiData(
      await http.get<ApiResponse<ConferenceSyncRun>>(
        `/api/v1/accounts/${accountId}/sync/conferences/${runId}`,
      ),
    )
  },
}
