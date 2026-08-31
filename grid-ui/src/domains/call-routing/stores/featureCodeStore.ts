import axios from 'axios'
import { defineStore } from 'pinia'
import { featureCodeApi } from '../api/featureCodeApi'
import type { FeatureCodeRoute } from '../types/featureCode'

export const useFeatureCodeStore = defineStore('feature-codes', {
  state: () => ({
    records: [] as FeatureCodeRoute[],
    total: 0,
    lastSuccessfulAt: null as string | null,
    loading: false,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.total = 0
      this.lastSuccessfulAt = null
      this.error = null
    },
    async load(accountId: string): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await featureCodeApi.list(accountId)
        this.records = response.data
        this.total = response.meta.total
        this.lastSuccessfulAt = response.meta.sync.last_successful_at
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load feature codes.')
          : 'Unable to load feature codes.'
      } finally {
        this.loading = false
      }
    },
  },
})
