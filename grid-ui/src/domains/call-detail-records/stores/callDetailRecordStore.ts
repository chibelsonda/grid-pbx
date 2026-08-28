import axios from 'axios'
import { defineStore } from 'pinia'
import { callDetailRecordApi } from '../api/callDetailRecordApi'
import type {
  CallDetailRecord,
  CallDetailRecordFilters,
  SyncState,
} from '../types/callDetailRecord'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }
const defaultFilters = (): CallDetailRecordFilters => ({
  search: '',
  direction: '',
  outcome: '',
  hangup_cause: '',
  started_from: '',
  started_to: '',
  duration_min: '',
  duration_max: '',
})

export const useCallDetailRecordStore = defineStore('call-detail-records', {
  state: () => ({
    records: [] as CallDetailRecord[],
    detail: null as CallDetailRecord | null,
    filters: defaultFilters(),
    sync: { ...defaultSync },
    importWindowDays: 7,
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    detailLoading: false,
    synchronizing: false,
    error: null as string | null,
    detailError: null as string | null,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.filters = defaultFilters()
      this.sync = { ...defaultSync }
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
      this.detailError = null
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await callDetailRecordApi.list(accountId, this.filters, page)
        this.records = response.data
        this.sync = response.meta.sync
        this.importWindowDays = response.meta.import_window_days
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load call history.')
          : 'Unable to load call history.'
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, recordId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null
      this.detail = null

      try {
        this.detail = await callDetailRecordApi.detail(accountId, recordId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the call record.')
          : 'Unable to load the call record.'
      } finally {
        this.detailLoading = false
      }
    },
    closeDetail(): void {
      this.detail = null
      this.detailError = null
    },
    clearFilters(): void {
      this.filters = defaultFilters()
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null

      try {
        let run = await callDetailRecordApi.startSync(accountId)

        for (
          let attempt = 0;
          attempt < 60 && ['queued', 'running'].includes(run.status);
          attempt += 1
        ) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await callDetailRecordApi.syncStatus(accountId, run.id)
        }

        if (run.status === 'failed')
          throw new Error(run.error_message ?? 'Call history sync failed.')
        if (run.status !== 'succeeded')
          throw new Error('Call history sync is still running. Reload shortly.')

        await this.load(accountId, 1)
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to synchronize call history.')
          : error instanceof Error
            ? error.message
            : 'Unable to synchronize call history.'
      } finally {
        this.synchronizing = false
      }
    },
  },
})
