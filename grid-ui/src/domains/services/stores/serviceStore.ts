import axios from 'axios'
import { defineStore } from 'pinia'
import { serviceApi } from '../api/serviceApi'
import type { ServiceOverview } from '../types/service'

const errorMessage = (error: unknown, fallback: string): string =>
  axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
export const useServiceStore = defineStore('services', {
  state: () => ({
    overview: null as ServiceOverview | null,
    loading: false,
    synchronizing: false,
    detailsOpen: false,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.overview = null
      this.loading = false
      this.synchronizing = false
      this.detailsOpen = false
      this.error = null
    },
    async load(accountId: string): Promise<void> {
      this.loading = true
      this.error = null
      try {
        this.overview = await serviceApi.overview(accountId)
      } catch (error) {
        this.error = errorMessage(error, 'Unable to load service information.')
      } finally {
        this.loading = false
      }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null
      try {
        const run = await serviceApi.synchronize(accountId)
        if (run.status !== 'succeeded')
          throw new Error(run.error_message ?? 'Service sync did not finish.')
        await this.load(accountId)
      } catch (error) {
        this.error =
          error instanceof Error
            ? error.message
            : errorMessage(error, 'Unable to synchronize services.')
      } finally {
        this.synchronizing = false
      }
    },
  },
})
