import axios from 'axios'
import { defineStore } from 'pinia'
import { mediaApi } from '../api/mediaApi'
import type { Media, MediaCreate, MediaFilters, MediaUpdate, SyncState } from '../types/media'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

function errorMessage(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

export const useMediaStore = defineStore('media', {
  state: () => ({
    records: [] as Media[],
    mohOptions: [] as Media[],
    detail: null as Media | null,
    filters: { search: '', media_source: '' } as MediaFilters,
    sync: { ...defaultSync },
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    detailLoading: false,
    mutationLoading: false,
    synchronizing: false,
    error: null as string | null,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    audioUrl: null as string | null,
    audioLoading: false,
  }),
  actions: {
    reset(): void {
      this.releaseAudio()
      this.records = []
      this.mohOptions = []
      this.detail = null
      this.sync = { ...defaultSync }
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
      this.clearMutationError()
    },
    clearMutationError(): void {
      this.mutationError = null
      this.fieldErrors = {}
    },
    captureMutationError(error: unknown, fallback: string): void {
      this.mutationError = errorMessage(error, fallback)
      this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true
      this.error = null
      try {
        const response = await mediaApi.list(accountId, this.filters, page)
        this.records = response.data
        this.sync = response.meta.sync
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load media.')
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, mediaId: string): Promise<void> {
      this.detailLoading = true
      this.error = null
      this.releaseAudio()
      try {
        this.detail = await mediaApi.detail(accountId, mediaId)
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load media details.')
      } finally {
        this.detailLoading = false
      }
    },
    async loadMohOptions(accountId: string): Promise<void> {
      try {
        const response = await mediaApi.list(accountId, { search: '', media_source: '' }, 1, 100)
        this.mohOptions = response.data
      } catch (error) {
        this.mutationError = errorMessage(error, 'Unable to load music-on-hold options.')
      }
    },
    async loadAudio(accountId: string, mediaId: string): Promise<void> {
      this.audioLoading = true
      this.releaseAudio()
      try {
        this.audioUrl = URL.createObjectURL(await mediaApi.audio(accountId, mediaId))
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load media audio.')
      } finally {
        this.audioLoading = false
      }
    },
    releaseAudio(): void {
      if (this.audioUrl) URL.revokeObjectURL(this.audioUrl)
      this.audioUrl = null
    },
    replaceRecord(record: Media): void {
      const index = this.records.findIndex((item) => item.id === record.id)
      if (index >= 0) this.records[index] = record
      else this.records.unshift(record)
      this.detail = record
    },
    async create(accountId: string, input: MediaCreate): Promise<boolean> {
      this.mutationLoading = true
      this.clearMutationError()
      try {
        this.replaceRecord(await mediaApi.create(accountId, input))
        this.total += 1
        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to upload media.')
        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async update(accountId: string, mediaId: string, input: MediaUpdate): Promise<boolean> {
      this.mutationLoading = true
      this.clearMutationError()
      try {
        this.replaceRecord(await mediaApi.update(accountId, mediaId, input))
        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to update media.')
        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async replaceAudio(accountId: string, mediaId: string, audio: File): Promise<boolean> {
      this.mutationLoading = true
      this.clearMutationError()
      try {
        this.replaceRecord(await mediaApi.replaceAudio(accountId, mediaId, audio))
        await this.loadAudio(accountId, mediaId)
        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to replace media audio.')
        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async remove(accountId: string, mediaId: string): Promise<boolean> {
      this.mutationLoading = true
      this.clearMutationError()
      try {
        await mediaApi.remove(accountId, mediaId)
        this.records = this.records.filter((record) => record.id !== mediaId)
        this.detail = null
        this.total = Math.max(0, this.total - 1)
        this.releaseAudio()
        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to delete media.')
        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async assignMusicOnHold(accountId: string, mediaId: string | null): Promise<boolean> {
      this.mutationLoading = true
      this.clearMutationError()
      try {
        const selected = await mediaApi.updateMusicOnHold(accountId, mediaId)
        this.records = this.records.map((record) => ({
          ...record,
          is_music_on_hold: record.id === selected?.id,
        }))
        this.mohOptions = this.mohOptions.map((record) => ({
          ...record,
          is_music_on_hold: record.id === selected?.id,
        }))
        if (this.detail)
          this.detail = { ...this.detail, is_music_on_hold: this.detail.id === selected?.id }
        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to update music on hold.')
        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null
      try {
        let run = await mediaApi.startSync(accountId)
        for (let attempt = 0; attempt < 40 && ['queued', 'running'].includes(run.status); attempt += 1) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await mediaApi.syncStatus(accountId, run.id)
        }
        if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Media sync did not finish.')
        await this.load(accountId, 1)
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? errorMessage(error, 'Unable to synchronize media.')
          : error instanceof Error
            ? error.message
            : 'Unable to synchronize media.'
      } finally {
        this.synchronizing = false
      }
    },
  },
})
