import axios from 'axios'
import { defineStore } from 'pinia'
import { conferenceApi } from '../api/conferenceApi'
import type { Conference, ConferenceInput, ConferenceOptions } from '../types/conference'

const emptyOptions: ConferenceOptions = { owners: [] }
function message(error: unknown, fallback: string): string { return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback }

export const useConferenceStore = defineStore('conferences', {
  state: () => ({
    records: [] as Conference[], detail: null as Conference | null, options: { ...emptyOptions }, search: '', status: '',
    page: 1, lastPage: 1, total: 0, loading: false, saving: false, synchronizing: false,
    error: null as string | null, mutationError: null as string | null, fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void { this.records = []; this.detail = null; this.options = { ...emptyOptions }; this.total = 0; this.error = null; this.clearMutationError() },
    clearMutationError(): void { this.mutationError = null; this.fieldErrors = {} },
    capture(error: unknown, fallback: string): void { this.mutationError = message(error, fallback); this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {} },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true; this.error = null
      try { const response = await conferenceApi.list(accountId, this.search, this.status, page); this.records = response.data; this.page = response.meta.current_page; this.lastPage = response.meta.last_page; this.total = response.meta.total }
      catch (error) { this.error = message(error, 'Unable to load conferences.') } finally { this.loading = false }
    },
    async prepare(accountId: string, id?: string): Promise<void> {
      this.loading = true; this.clearMutationError()
      try { const [options, detail] = await Promise.all([conferenceApi.options(accountId), id ? conferenceApi.detail(accountId, id) : Promise.resolve(null)]); this.options = options; this.detail = detail }
      catch (error) { this.error = message(error, 'Unable to prepare the conference panel.') } finally { this.loading = false }
    },
    replace(record: Conference): void { const index = this.records.findIndex(({ id }) => id === record.id); if (index >= 0) this.records[index] = record; else this.records.unshift(record); this.detail = record },
    async save(accountId: string, input: ConferenceInput): Promise<boolean> {
      this.saving = true; this.clearMutationError()
      try { const isNew = !this.detail; this.replace(isNew ? await conferenceApi.create(accountId, input) : await conferenceApi.update(accountId, this.detail!.id, input)); if (isNew) this.total += 1; await this.load(accountId, this.page); return true }
      catch (error) { this.capture(error, 'Unable to save conference.'); return false } finally { this.saving = false }
    },
    async remove(accountId: string): Promise<boolean> {
      if (!this.detail) return false; this.saving = true; this.clearMutationError()
      try { const id = this.detail.id; await conferenceApi.remove(accountId, id); this.records = this.records.filter((record) => record.id !== id); this.detail = null; this.total = Math.max(0, this.total - 1); await this.load(accountId, this.page); return true }
      catch (error) { this.capture(error, 'Unable to delete conference.'); return false } finally { this.saving = false }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true; this.error = null
      try { let run = await conferenceApi.startSync(accountId); for (let attempt = 0; attempt < 40 && ['queued', 'running'].includes(run.status); attempt += 1) { await new Promise((resolve) => window.setTimeout(resolve, 500)); run = await conferenceApi.syncStatus(accountId, run.id) } if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Conference sync did not finish.'); await this.load(accountId) }
      catch (error) { this.error = error instanceof Error ? error.message : message(error, 'Unable to synchronize conferences.') } finally { this.synchronizing = false }
    },
  },
})
