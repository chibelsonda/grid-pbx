import axios from 'axios'
import { defineStore } from 'pinia'
import { phoneNumberApi } from '../api/phoneNumberApi'
import type { PhoneNumber, PhoneNumberFilters, SyncState } from '../types/phoneNumber'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

export const usePhoneNumberStore = defineStore('phone-numbers', {
  state: () => ({
    records: [] as PhoneNumber[],
    detail: null as PhoneNumber | null,
    filters: {
      search: '',
      state: '',
      assignment: '',
      feature: '',
    } as PhoneNumberFilters,
    sync: { ...defaultSync },
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
        const response = await phoneNumberApi.list(accountId, this.filters, page)
        this.records = response.data
        this.sync = response.meta.sync
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load phone numbers.')
          : 'Unable to load phone numbers.'
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, phoneNumberId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null
      this.detail = null

      try {
        this.detail = await phoneNumberApi.detail(accountId, phoneNumberId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the phone number.')
          : 'Unable to load the phone number.'
      } finally {
        this.detailLoading = false
      }
    },
    closeDetail(): void {
      this.detail = null
      this.detailError = null
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null

      try {
        let run = await phoneNumberApi.startSync(accountId)

        for (
          let attempt = 0;
          attempt < 40 && ['queued', 'running'].includes(run.status);
          attempt += 1
        ) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await phoneNumberApi.syncStatus(accountId, run.id)
        }

        if (run.status === 'failed')
          throw new Error(run.error_message ?? 'Phone number sync failed.')
        if (run.status !== 'succeeded')
          throw new Error('Phone number sync is still running. Reload shortly.')

        await this.load(accountId, 1)
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to synchronize phone numbers.')
          : error instanceof Error
            ? error.message
            : 'Unable to synchronize phone numbers.'
      } finally {
        this.synchronizing = false
      }
    },
  },
})
