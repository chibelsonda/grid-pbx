import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from 'axios'
import type { PaginatedResponse } from '@/shared/api/http'
import { conferenceApi } from '../api/conferenceApi'
import type {
  Conference,
  ConferenceInput,
  ConferenceOptions,
  ConferenceSyncRun,
} from '../types/conference'
import { useConferenceStore } from './conferenceStore'

vi.mock('../api/conferenceApi', () => ({
  conferenceApi: {
    list: vi.fn<() => Promise<PaginatedResponse<Conference>>>(),
    detail: vi.fn<() => Promise<Conference>>(),
    options: vi.fn<() => Promise<ConferenceOptions>>(),
    create: vi.fn<() => Promise<Conference>>(),
    update: vi.fn<() => Promise<Conference>>(),
    remove: vi.fn<() => Promise<void>>(),
    control: vi.fn(),
    participants: vi.fn(),
    controlParticipant: vi.fn(),
    controlParticipants: vi.fn(),
    playMedia: vi.fn(),
    startSync: vi.fn<() => Promise<ConferenceSyncRun>>(),
    syncStatus: vi.fn<() => Promise<ConferenceSyncRun>>(),
  },
}))
const record: Conference = {
  id: 'public-conference',
  name: 'Daily standup',
  owner: null,
  conference_numbers: ['7000'],
  member_numbers: ['7001'],
  moderator_numbers: ['7099'],
  member_pin_configured: true,
  moderator_pin_configured: true,
  member_join_muted: true,
  member_join_deaf: false,
  member_play_entry_prompt: false,
  moderator_join_muted: false,
  moderator_join_deaf: false,
  max_participants: 50,
  language: 'en-US',
  profile_name: null,
  caller_controls: null,
  moderator_controls: null,
  play_name: false,
  play_welcome: true,
  require_moderator: true,
  wait_for_moderator: true,
  max_members_media: null,
  entry_tone: { mode: 'enabled', media: null },
  exit_tone: { mode: 'enabled', media: null },
  runtime: { members: 2, moderators: 1, duration_seconds: 90, is_locked: false },
  sync_status: 'healthy',
  last_synced_at: null,
}
const input: ConferenceInput = {
  name: 'Daily standup',
  owner_id: null,
  conference_numbers: ['7000'],
  member_numbers: ['7001'],
  moderator_numbers: ['7099'],
  member_pins: [],
  clear_member_pin: false,
  moderator_pins: [],
  clear_moderator_pin: false,
  member_join_muted: true,
  member_join_deaf: false,
  member_play_entry_prompt: false,
  moderator_join_muted: false,
  moderator_join_deaf: false,
  max_participants: 50,
  language: 'en-US',
  profile_name: null,
  caller_controls: null,
  moderator_controls: null,
  play_name: false,
  play_welcome: true,
  require_moderator: true,
  wait_for_moderator: true,
  max_members_media_id: null,
  play_entry_tone_mode: 'enabled',
  play_entry_tone_media_id: null,
  play_exit_tone_mode: 'enabled',
  play_exit_tone_media_id: null,
}
const response: PaginatedResponse<Conference> = {
  data: [record],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
}

describe('conference store', () => {
  beforeEach(() => {
    vi.useRealTimers()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })
  it('loads account-scoped conferences with filters', async () => {
    vi.mocked(conferenceApi.list).mockResolvedValue(response)
    const store = useConferenceStore()
    store.status = 'active'
    await store.load('account-1')
    expect(conferenceApi.list).toHaveBeenCalledWith('account-1', '', 'active', 1)
    expect(store.records).toEqual([record])
  })
  it('creates a conference without reading current pins', async () => {
    vi.mocked(conferenceApi.options).mockResolvedValue({
      owners: [],
      media: [],
      playable_media: [],
    })
    vi.mocked(conferenceApi.create).mockResolvedValue(record)
    vi.mocked(conferenceApi.list).mockResolvedValue(response)
    const store = useConferenceStore()
    await store.prepare('account-1')
    expect(await store.save('account-1', input)).toBe(true)
    expect(conferenceApi.create).toHaveBeenCalledWith('account-1', input)
  })
  it('keeps server validation inline without a duplicate mutation alert', async () => {
    vi.mocked(conferenceApi.create).mockRejectedValue(
      new axios.AxiosError('Validation failed', '422', undefined, undefined, {
        data: { message: 'Validation failed.', errors: { name: ['Enter a conference name.'] } },
        status: 422,
        statusText: 'Unprocessable Content',
        headers: {},
        config: { headers: {} },
      } as never),
    )
    const store = useConferenceStore()

    expect(await store.save('account-1', input)).toBe(false)
    expect(store.fieldErrors.name).toEqual(['Enter a conference name.'])
    expect(store.mutationError).toBeNull()
  })
  it('submits a room control command and synchronizes the observed Switch state', async () => {
    vi.mocked(conferenceApi.control).mockResolvedValue({
      accepted: true,
      action: 'lock',
      message: 'Switch accepted the conference lock request.',
    })
    vi.mocked(conferenceApi.startSync).mockResolvedValue({
      id: 'sync-1',
      status: 'succeeded',
      error_message: null,
    })
    vi.mocked(conferenceApi.list).mockResolvedValue(response)
    const store = useConferenceStore()

    expect(await store.control('account-1', record, 'lock')).toBe(true)
    expect(conferenceApi.control).toHaveBeenCalledWith('account-1', record.id, 'lock')
    expect(conferenceApi.startSync).toHaveBeenCalledWith('account-1')
    expect(store.controllingId).toBeNull()
  })
  it('uses the opaque participant handle for a command and refreshes runtime state', async () => {
    const participant = {
      id: 'opaque-participant-handle',
      display_name: 'Ada Lovelace',
      number: '1001',
      is_moderator: false,
      can_speak: true,
      can_hear: true,
      duration_seconds: 30,
    }
    vi.mocked(conferenceApi.controlParticipant).mockResolvedValue({
      accepted: true,
      action: 'mute',
      message: 'Switch accepted the participant mute request.',
    })
    vi.mocked(conferenceApi.startSync).mockResolvedValue({
      id: 'sync-1',
      status: 'succeeded',
      error_message: null,
    })
    vi.mocked(conferenceApi.list).mockResolvedValue(response)
    vi.mocked(conferenceApi.participants).mockResolvedValue([{ ...participant, can_speak: false }])
    const store = useConferenceStore()

    expect(await store.controlParticipant('account-1', record, participant, 'mute')).toBe(true)
    expect(conferenceApi.controlParticipant).toHaveBeenCalledWith(
      'account-1',
      record.id,
      participant.id,
      'mute',
    )
    expect(store.participants[0]?.can_speak).toBe(false)
    expect(store.participantControlId).toBeNull()
  })
  it('submits a confirmed room preview and refreshes participants after bulk control', async () => {
    const participant = {
      id: 'opaque-participant-handle',
      display_name: 'Ada Lovelace',
      number: '1001',
      is_moderator: false,
      can_speak: true,
      can_hear: true,
      duration_seconds: 30,
    }
    vi.mocked(conferenceApi.controlParticipants).mockResolvedValue({
      accepted: true,
      action: 'mute',
      targeted_participants: 1,
      skipped_moderators: 0,
      message: 'Switch accepted the room-wide mute request for 1 participant(s).',
    })
    vi.mocked(conferenceApi.participants).mockResolvedValue([{ ...participant, can_speak: false }])
    const store = useConferenceStore()

    expect(await store.controlParticipants('account-1', record, 'mute', 1, 1)).toBe(true)
    expect(conferenceApi.controlParticipants).toHaveBeenCalledWith(
      'account-1',
      record.id,
      'mute',
      1,
      1,
    )
    expect(store.participants[0]?.can_speak).toBe(false)
    expect(store.bulkControllingAction).toBeNull()
    expect(store.bulkControlObservation).toEqual({
      action: 'mute',
      status: 'observed',
      targeted_participants: 1,
      observed_participants: 1,
      message: 'Observed the requested state for all 1 targeted participant(s).',
    })
  })
  it('bounds reconciliation and reports acceptance as pending when state is not yet observed', async () => {
    vi.useFakeTimers()
    const participant = {
      id: 'opaque-participant-handle',
      display_name: 'Ada Lovelace',
      number: '1001',
      is_moderator: false,
      can_speak: true,
      can_hear: true,
      duration_seconds: 30,
    }
    vi.mocked(conferenceApi.controlParticipants).mockResolvedValue({
      accepted: true,
      action: 'mute',
      targeted_participants: 1,
      skipped_moderators: 0,
      message: 'Switch accepted the room-wide mute request for 1 participant(s).',
    })
    vi.mocked(conferenceApi.participants).mockResolvedValue([participant])
    const store = useConferenceStore()

    const command = store.controlParticipants('account-1', record, 'mute', 1, 1)
    await vi.runAllTimersAsync()

    expect(await command).toBe(true)
    expect(conferenceApi.participants).toHaveBeenCalledTimes(4)
    expect(store.bulkControlObservation).toEqual({
      action: 'mute',
      status: 'pending',
      targeted_participants: 1,
      observed_participants: 0,
      message:
        'Switch accepted the command; 0 of 1 targeted participant(s) are currently observed in the requested state.',
    })
  })
})
