import axios from 'axios'
import { defineStore } from 'pinia'
import { lineKeyApi } from '../api/lineKeyApi'
import type {
  LineKeyDevice,
  LineKeyInput,
  LineKeyPreview,
  LineKeySyncRun,
  LineKeySyncState,
} from '../types/lineKey'

const defaultSync: LineKeySyncState = {
  status: 'stale',
  last_successful_at: null,
  error_message: null,
}

const message = (error: unknown, fallback: string): string =>
  axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback

export const useLineKeyStore = defineStore('lineKeys', {
  state: () => ({
    records: [] as LineKeyDevice[],
    preview: null as LineKeyPreview | null,
    sync: { ...defaultSync },
    syncRun: null as LineKeySyncRun | null,
    search: '',
    loading: false,
    synchronizing: false,
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
      this.sync = { ...defaultSync }
      this.syncRun = null
      this.error = null
      this.synchronizing = false
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
        const response = await lineKeyApi.list(accountId, this.search)
        this.records = response.data
        this.sync = response.meta.sync
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
    async synchronize(accountId: string): Promise<void> {
      if (this.synchronizing) return

      this.synchronizing = true
      this.error = null
      this.sync = { ...this.sync, status: 'syncing', error_message: null }
      this.syncRun = null

      try {
        let run = await lineKeyApi.startSync(accountId)
        this.syncRun = run

        for (
          let attempt = 0;
          attempt < 40 && ['queued', 'running'].includes(run.status);
          attempt += 1
        ) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await lineKeyApi.syncStatus(accountId, run.id)
          this.syncRun = run
        }

        if (run.status === 'failed')
          throw new Error(run.error_message ?? 'Switch synchronization failed.')
        if (run.status !== 'succeeded')
          throw new Error('Switch synchronization is still running. Reload shortly.')

        await this.load(accountId)
      } catch (error) {
        const errorMessage = axios.isAxiosError(error)
          ? message(error, 'Unable to synchronize line-key projections.')
          : error instanceof Error
            ? error.message
            : 'Unable to synchronize line-key projections.'
        this.error = errorMessage
        this.sync = { ...this.sync, status: 'error', error_message: errorMessage }
      } finally {
        this.synchronizing = false
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
        if (axios.isAxiosError(error)) {
          this.fieldErrors = error.response?.data?.errors ?? {}
          this.mutationError = Object.keys(this.fieldErrors).length
            ? null
            : (error.response?.data?.message ?? 'Unable to apply line-key configuration.')
        } else {
          this.fieldErrors = {}
          this.mutationError = 'Unable to apply line-key configuration.'
        }
        return false
      } finally {
        this.saving = false
      }
    },
  },
})
