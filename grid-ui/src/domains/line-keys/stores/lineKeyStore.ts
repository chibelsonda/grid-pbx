import axios from 'axios'
import { defineStore } from 'pinia'
import { lineKeyApi } from '../api/lineKeyApi'
import type { LineKeyDevice, LineKeyInput, LineKeyPreview } from '../types/lineKey'

const message = (error: unknown, fallback: string): string =>
  axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback

export const useLineKeyStore = defineStore('lineKeys', {
  state: () => ({
    records: [] as LineKeyDevice[],
    preview: null as LineKeyPreview | null,
    search: '',
    loading: false,
    previewLoading: false,
    saving: false,
    error: null as string | null,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.preview = null
      this.error = null
      this.clearMutationError()
    },
    clearMutationError(): void {
      this.mutationError = null
      this.fieldErrors = {}
    },
    async load(accountId: string): Promise<void> {
      this.loading = true
      this.error = null
      try {
        this.records = await lineKeyApi.list(accountId, this.search)
      } catch (error) {
        this.error = message(error, 'Unable to load line keys.')
      } finally {
        this.loading = false
      }
    },
    async prepare(accountId: string, deviceId: string): Promise<void> {
      this.previewLoading = true
      this.preview = null
      this.clearMutationError()
      try {
        this.preview = await lineKeyApi.preview(accountId, deviceId)
      } catch (error) {
        this.mutationError = message(error, 'Unable to prepare the provisioning preview.')
      } finally {
        this.previewLoading = false
      }
    },
    async save(accountId: string, keys: LineKeyInput[]): Promise<boolean> {
      if (!this.preview) return false
      this.saving = true
      this.clearMutationError()
      try {
        await lineKeyApi.update(accountId, this.preview.device.id, keys)
        await this.load(accountId)
        return true
      } catch (error) {
        this.mutationError = message(error, 'Unable to apply line-key configuration.')
        this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
        return false
      } finally {
        this.saving = false
      }
    },
  },
})
