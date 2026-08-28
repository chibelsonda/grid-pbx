import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { PaginatedResponse } from '@/shared/api/http'
import { conferenceApi } from '../api/conferenceApi'
import type { Conference, ConferenceInput, ConferenceOptions, ConferenceSyncRun } from '../types/conference'
import { useConferenceStore } from './conferenceStore'

vi.mock('../api/conferenceApi', () => ({ conferenceApi: {
  list: vi.fn<() => Promise<PaginatedResponse<Conference>>>(), detail: vi.fn<() => Promise<Conference>>(), options: vi.fn<() => Promise<ConferenceOptions>>(),
  create: vi.fn<() => Promise<Conference>>(), update: vi.fn<() => Promise<Conference>>(), remove: vi.fn<() => Promise<void>>(),
  startSync: vi.fn<() => Promise<ConferenceSyncRun>>(), syncStatus: vi.fn<() => Promise<ConferenceSyncRun>>(),
} }))
const record: Conference = {
  id: 'public-conference', name: 'Daily standup', owner: null, conference_numbers: ['7000'], member_numbers: ['7001'], moderator_numbers: ['7099'],
  member_pin_configured: true, moderator_pin_configured: true, member_join_muted: true, member_join_deaf: false, member_play_entry_prompt: false,
  moderator_join_muted: false, moderator_join_deaf: false, max_participants: 50, language: 'en-US', profile_name: null, caller_controls: null, moderator_controls: null,
  play_name: false, play_welcome: true, require_moderator: true, wait_for_moderator: true, runtime: { members: 2, moderators: 1, duration_seconds: 90, is_locked: false }, sync_status: 'healthy', last_synced_at: null,
}
const input: ConferenceInput = {
  name: 'Daily standup', owner_id: null, conference_numbers: ['7000'], member_numbers: ['7001'], moderator_numbers: ['7099'], member_pin: null, clear_member_pin: false,
  moderator_pin: null, clear_moderator_pin: false, member_join_muted: true, member_join_deaf: false, member_play_entry_prompt: false, moderator_join_muted: false,
  moderator_join_deaf: false, max_participants: 50, language: 'en-US', profile_name: null, caller_controls: null, moderator_controls: null, play_name: false,
  play_welcome: true, require_moderator: true, wait_for_moderator: true,
}
const response: PaginatedResponse<Conference> = { data: [record], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 } }

describe('conference store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })
  it('loads account-scoped conferences with filters', async () => {
    vi.mocked(conferenceApi.list).mockResolvedValue(response); const store = useConferenceStore(); store.status = 'active'; await store.load('account-1')
    expect(conferenceApi.list).toHaveBeenCalledWith('account-1', '', 'active', 1); expect(store.records).toEqual([record])
  })
  it('creates a conference without reading current pins', async () => {
    vi.mocked(conferenceApi.options).mockResolvedValue({ owners: [] }); vi.mocked(conferenceApi.create).mockResolvedValue(record); vi.mocked(conferenceApi.list).mockResolvedValue(response)
    const store = useConferenceStore(); await store.prepare('account-1'); expect(await store.save('account-1', input)).toBe(true); expect(conferenceApi.create).toHaveBeenCalledWith('account-1', input)
  })
})
