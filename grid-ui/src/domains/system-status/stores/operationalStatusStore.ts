import axios from 'axios'
import { defineStore } from 'pinia'
import { operationalStatusApi } from '../api/operationalStatusApi'
import type { OperationalStatus } from '../schemas/operationalStatusSchema'

function errorMessage(error: unknown): string {
  return axios.isAxiosError(error)
    ? (error.response?.data?.message ?? 'Unable to load operational status.')
    : 'Unable to load operational status.'
}

export const useOperationalStatusStore = defineStore('operational-status', {
  state: () => ({
    status: null as OperationalStatus | null,
    loading: false,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.status = null
      this.loading = false
      this.error = null
    },
    async load(accountId: string): Promise<void> {
      this.loading = true
      this.error = null

      try {
        this.status = await operationalStatusApi.get(accountId)
      } catch (error) {
        this.error = errorMessage(error)
      } finally {
        this.loading = false
      }
    },
  },
})
