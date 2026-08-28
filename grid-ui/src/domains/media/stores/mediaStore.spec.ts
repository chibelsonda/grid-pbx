import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mediaApi, type MediaPage } from '../api/mediaApi'
import type { Media, MediaCreate, SyncRun } from '../types/media'
import { useMediaStore } from './mediaStore'

vi.mock('../api/mediaApi', () => ({
  mediaApi: {
    list: vi.fn<() => Promise<MediaPage>>(),
    detail: vi.fn<() => Promise<Media>>(),
    create: vi.fn<() => Promise<Media>>(),
    update: vi.fn<() => Promise<Media>>(),
    replaceAudio: vi.fn<() => Promise<Media>>(),
    remove: vi.fn<() => Promise<void>>(),
    updateMusicOnHold: vi.fn<() => Promise<Media | null>>(),
    audio: vi.fn<() => Promise<Blob>>(),
    startSync: vi.fn<() => Promise<SyncRun>>(),
    syncStatus: vi.fn<() => Promise<SyncRun>>(),
  },
}))

const record: Media = {
  id: 'media-public-id',
  name: 'Main hold music',
  description: 'Lobby loop',
  language: 'en-us',
  media_source: 'upload',
  content_type: 'audio/mpeg',
  content_length: 4096,
  prompt_id: null,
  streamable: true,
  is_music_on_hold: false,
  last_synced_at: '2026-08-28T05:00:00Z',
  sync_status: 'healthy',
  created_at: '2026-08-28T04:00:00Z',
  updated_at: '2026-08-28T05:00:00Z',
}

describe('media store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads projected media with sync metadata', async () => {
    vi.mocked(mediaApi.list).mockResolvedValue({
      data: [record],
      links: { prev: null, next: null },
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 1,
        sync: { status: 'healthy', last_successful_at: record.last_synced_at, error_message: null },
      },
    })
    const store = useMediaStore()

    await store.load('account-1')

    expect(mediaApi.list).toHaveBeenCalledWith('account-1', store.filters, 1)
    expect(store.records).toEqual([record])
    expect(store.sync.status).toBe('healthy')
  })

  it('adds an uploaded media projection to local state', async () => {
    vi.mocked(mediaApi.create).mockResolvedValue(record)
    const store = useMediaStore()
    const input: MediaCreate = {
      name: record.name,
      description: record.description,
      language: record.language,
      streamable: true,
      audio: new File(['MP3!'], 'hold.mp3', { type: 'audio/mpeg' }),
    }

    const created = await store.create('account-1', input)

    expect(created).toBe(true)
    expect(mediaApi.create).toHaveBeenCalledWith('account-1', input)
    expect(store.records).toEqual([record])
    expect(store.total).toBe(1)
  })

  it('updates music-on-hold flags without exposing Switch identifiers', async () => {
    vi.mocked(mediaApi.updateMusicOnHold).mockResolvedValue({ ...record, is_music_on_hold: true })
    const store = useMediaStore()
    store.records = [record, { ...record, id: 'other-media' }]

    const saved = await store.assignMusicOnHold('account-1', record.id)

    expect(saved).toBe(true)
    expect(store.records[0]?.is_music_on_hold).toBe(true)
    expect(store.records[1]?.is_music_on_hold).toBe(false)
  })
})
