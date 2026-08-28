import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { recordingApi, type RecordingPage } from '../api/recordingApi'
import type { Recording, RecordingSyncRun } from '../types/recording'
import { useRecordingStore } from './recordingStore'

vi.mock('../api/recordingApi', () => ({ recordingApi: { list: vi.fn<() => Promise<RecordingPage>>(), detail: vi.fn<() => Promise<Recording>>(), audio: vi.fn<() => Promise<Blob>>(), startSync: vi.fn<() => Promise<RecordingSyncRun>>(), syncStatus: vi.fn<() => Promise<RecordingSyncRun>>() } }))
const record: Recording = { id: 'public-recording', call_id: 'call-1', interaction_id: 'interaction-1', direction: 'inbound', caller: { name: 'Caller', number: '+15550001000' }, callee: { name: 'Support', number: '+15550002000' }, from: null, to: null, request: null, started_at: '2026-08-28T00:00:00Z', duration_seconds: 42, duration_milliseconds: 42000, name: 'Support call', description: null, content_type: 'audio/mpeg', content_length: 4, media_source: 'recording', media_type: 'mp3', source_type: null, origin: null, has_audio: true, extension: null, call_detail_record_id: null, last_synced_at: null, sync_status: 'healthy' }
const page: RecordingPage = { data: [record], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, last_page: 1, per_page: 25, total: 1, sync: { status: 'healthy', last_successful_at: null, error_message: null }, import_window_days: 31 } }
describe('recording store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks(); vi.stubGlobal('URL', { createObjectURL: vi.fn(() => 'blob:recording'), revokeObjectURL: vi.fn() }) })
  it('loads bounded account recording metadata', async () => { vi.mocked(recordingApi.list).mockResolvedValue(page); const store = useRecordingStore(); await store.load('account-1'); expect(recordingApi.list).toHaveBeenCalledWith('account-1', store.filters, 1); expect(store.records).toEqual([record]); expect(store.importWindowDays).toBe(31) })
  it('loads protected audio only after authorized detail metadata', async () => { vi.mocked(recordingApi.detail).mockResolvedValue(record); vi.mocked(recordingApi.audio).mockResolvedValue(new Blob(['MP3!'], { type: 'audio/mpeg' })); const store = useRecordingStore(); await store.loadDetail('account-1', record.id); expect(recordingApi.detail).toHaveBeenCalledWith('account-1', record.id); expect(recordingApi.audio).toHaveBeenCalledWith('account-1', record.id, false); expect(store.audioUrl).toBe('blob:recording') })
})
