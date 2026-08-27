import axios from 'axios'
import { defineStore } from 'pinia'
import { extensionApi } from '../api/extensionApi'
import type { Extension, SyncRun, SyncState } from '../types/extension'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

export const useExtensionStore = defineStore('extensions', {
  state: () => ({
    records: [] as Extension[],
    sync: { ...defaultSync },
    currentRun: null as SyncRun | null,
    search: '',
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    syncing: false,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.sync = { ...defaultSync }
      this.currentRun = null
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
    },
    async load(accountId: string, page?: number): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await extensionApi.list(accountId, this.search, page ?? this.page)
        this.records = response.data
        this.sync = response.meta.sync
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = axios.isAxiosError(error) ? (error.response?.data?.message ?? 'Unable to load extensions.') : 'Unable to load extensions.'
      } finally {
        this.loading = false
      }
    },
    async startSync(accountId: string): Promise<void> {
      this.syncing = true
      this.error = null

      try {
        this.currentRun = await extensionApi.startSync(accountId)
        this.sync.status = 'syncing'

        for (let attempt = 0; attempt < 40; attempt += 1) {
          await new Promise((resolve) => window.setTimeout(resolve, 1500))
          this.currentRun = await extensionApi.syncRun(accountId, this.currentRun.id)

          if (this.currentRun.status === 'succeeded') {
            await this.load(accountId, 1)
            return
          }

          if (this.currentRun.status === 'failed') {
            this.error = this.currentRun.error_message ?? 'Kazoo synchronization failed.'
            return
          }
        }

        this.error = 'The synchronization is still running. Refresh shortly to see the result.'
      } catch (error) {
        this.error = axios.isAxiosError(error) ? (error.response?.data?.message ?? 'Unable to start synchronization.') : 'Unable to start synchronization.'
      } finally {
        this.syncing = false
      }
    },
  },
})
