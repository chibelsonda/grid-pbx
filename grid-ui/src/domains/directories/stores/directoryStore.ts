import axios from 'axios'
import { defineStore } from 'pinia'
import { directoryApi } from '../api/directoryApi'
import type { Directory, DirectoryInput, DirectoryOptions } from '../types/directory'

function message(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

export const useDirectoryStore = defineStore('directories', {
  state: () => ({
    records: [] as Directory[], detail: null as Directory | null,
    options: { extensions: [] } as DirectoryOptions, search: '', page: 1, lastPage: 1, total: 0,
    loading: false, saving: false, synchronizing: false, error: null as string | null, mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void { this.records = []; this.detail = null; this.page = 1; this.lastPage = 1; this.total = 0; this.error = null; this.clearMutationError() },
    clearMutationError(): void { this.mutationError = null; this.fieldErrors = {} },
    capture(error: unknown, fallback: string): void {
      this.mutationError = message(error, fallback)
      this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true; this.error = null
      try { const response = await directoryApi.list(accountId, this.search, page); this.records = response.data; this.page = response.meta.current_page; this.lastPage = response.meta.last_page; this.total = response.meta.total }
      catch (error) { this.error = message(error, 'Unable to load directories.') }
      finally { this.loading = false }
    },
    async prepare(accountId: string, id?: string): Promise<void> {
      this.loading = true; this.clearMutationError()
      try { const [options, detail] = await Promise.all([directoryApi.options(accountId), id ? directoryApi.detail(accountId, id) : Promise.resolve(null)]); this.options = options; this.detail = detail }
      catch (error) { this.error = message(error, 'Unable to prepare the directory form.') }
      finally { this.loading = false }
    },
    replace(record: Directory): void { const index = this.records.findIndex(({ id }) => id === record.id); if (index >= 0) this.records[index] = record; else this.records.unshift(record); this.detail = record },
    async save(accountId: string, input: DirectoryInput): Promise<boolean> {
      this.saving = true; this.clearMutationError()
      try { const isNew = !this.detail; this.replace(isNew ? await directoryApi.create(accountId, input) : await directoryApi.update(accountId, this.detail!.id, input)); if (isNew) this.total += 1; return true }
      catch (error) { this.capture(error, 'Unable to save directory.'); return false }
      finally { this.saving = false }
    },
    async remove(accountId: string): Promise<boolean> {
      if (!this.detail) return false
      this.saving = true; this.clearMutationError()
      try { const id = this.detail.id; await directoryApi.remove(accountId, id); this.records = this.records.filter((record) => record.id !== id); this.detail = null; this.total = Math.max(0, this.total - 1); return true }
      catch (error) { this.capture(error, 'Unable to delete directory.'); return false }
      finally { this.saving = false }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true; this.error = null
      try {
        let run = await directoryApi.startSync(accountId)
        for (let attempt = 0; attempt < 40 && ['queued', 'running'].includes(run.status); attempt += 1) {
          await new Promise((resolve) => window.setTimeout(resolve, 500)); run = await directoryApi.syncStatus(accountId, run.id)
        }
        if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Directory sync did not finish.')
        await this.load(accountId)
      } catch (error) { this.error = error instanceof Error ? error.message : message(error, 'Unable to synchronize directories.') }
      finally { this.synchronizing = false }
    },
  },
})
