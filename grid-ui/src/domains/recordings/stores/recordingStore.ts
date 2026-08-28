import axios from 'axios'
import { defineStore } from 'pinia'
import { recordingApi } from '../api/recordingApi'
import type { Recording, RecordingFilters, RecordingSyncState } from '../types/recording'

const defaultFilters = (): RecordingFilters => ({ search: '', direction: '', started_from: '', started_to: '', duration_min: '', duration_max: '', has_audio: '1' })
const defaultSync = (): RecordingSyncState => ({ status: 'stale', last_successful_at: null, error_message: null })
const errorMessage = (error: unknown, fallback: string) => axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
export const useRecordingStore = defineStore('recordings', {
  state: () => ({ records: [] as Recording[], detail: null as Recording | null, filters: defaultFilters(), sync: defaultSync(), importWindowDays: 31, page: 1, lastPage: 1, total: 0, loading: false, detailLoading: false, audioLoading: false, synchronizing: false, error: null as string | null, detailError: null as string | null, audioUrl: null as string | null }),
  actions: {
    reset(): void { this.releaseAudio(); this.records = []; this.detail = null; this.filters = defaultFilters(); this.sync = defaultSync(); this.page = 1; this.lastPage = 1; this.total = 0; this.error = null; this.detailError = null },
    clearFilters(): void { this.filters = defaultFilters() },
    async load(accountId: string, page = 1): Promise<void> { this.loading = true; this.error = null; try { const response = await recordingApi.list(accountId, this.filters, page); this.records = response.data; this.sync = response.meta.sync; this.importWindowDays = response.meta.import_window_days; this.page = response.meta.current_page; this.lastPage = response.meta.last_page; this.total = response.meta.total } catch (error) { this.error = errorMessage(error, 'Unable to load recordings.') } finally { this.loading = false } },
    async loadDetail(accountId: string, id: string): Promise<void> { this.detailLoading = true; this.detailError = null; this.releaseAudio(); try { this.detail = await recordingApi.detail(accountId, id); if (this.detail.has_audio) await this.loadAudio(accountId, id) } catch (error) { this.detailError = errorMessage(error, 'Unable to load recording details.') } finally { this.detailLoading = false } },
    async loadAudio(accountId: string, id: string): Promise<void> { this.audioLoading = true; try { this.audioUrl = URL.createObjectURL(await recordingApi.audio(accountId, id, false)) } catch (error) { this.detailError = errorMessage(error, 'Unable to load recording audio.') } finally { this.audioLoading = false } },
    async downloadAudio(accountId: string, id: string): Promise<void> { this.audioLoading = true; try { const url = URL.createObjectURL(await recordingApi.audio(accountId, id, true)); const link = document.createElement('a'); link.href = url; link.download = `recording-${id}.mp3`; link.click(); URL.revokeObjectURL(url) } catch (error) { this.detailError = errorMessage(error, 'Unable to download recording audio.') } finally { this.audioLoading = false } },
    closeDetail(): void { this.releaseAudio(); this.detail = null; this.detailError = null },
    releaseAudio(): void { if (this.audioUrl) URL.revokeObjectURL(this.audioUrl); this.audioUrl = null },
    async synchronize(accountId: string): Promise<void> { this.synchronizing = true; this.error = null; try { let run = await recordingApi.startSync(accountId); for (let i = 0; i < 40 && ['queued', 'running'].includes(run.status); i += 1) { await new Promise((resolve) => window.setTimeout(resolve, 500)); run = await recordingApi.syncStatus(accountId, run.id) } if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Recording sync did not finish.'); await this.load(accountId, 1) } catch (error) { this.error = error instanceof Error ? error.message : errorMessage(error, 'Unable to synchronize recordings.') } finally { this.synchronizing = false } },
  },
})
