import axios from 'axios'
import { defineStore } from 'pinia'
import { resellerApi } from '../api/resellerApi'
import type {
  AccountHierarchy,
  DescendantOnboardingCandidates,
  DescendantOnboardingInput,
  ResellerStatus,
} from '../types/reseller'

const errorMessage = (error: unknown): string =>
  axios.isAxiosError(error)
    ? (error.response?.data?.message ?? 'Unable to load reseller administration information.')
    : 'Unable to load reseller administration information.'

export const useResellerStore = defineStore('reseller', {
  state: () => ({
    hierarchy: null as AccountHierarchy | null,
    status: null as ResellerStatus | null,
    onboardingCandidates: null as DescendantOnboardingCandidates | null,
    loading: false,
    candidatesLoading: false,
    onboarding: false,
    error: null as string | null,
    onboardingError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void {
      this.hierarchy = null
      this.status = null
      this.onboardingCandidates = null
      this.loading = false
      this.candidatesLoading = false
      this.onboarding = false
      this.error = null
      this.onboardingError = null
      this.fieldErrors = {}
    },

    async load(accountId: string): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const [hierarchy, status] = await Promise.all([
          resellerApi.hierarchy(accountId),
          resellerApi.status(accountId),
        ])

        this.hierarchy = hierarchy
        this.status = status
      } catch (error) {
        this.hierarchy = null
        this.status = null
        this.error = errorMessage(error)
      } finally {
        this.loading = false
      }
    },

    async loadOnboardingCandidates(accountId: string): Promise<void> {
      this.candidatesLoading = true
      this.onboardingError = null
      this.fieldErrors = {}

      try {
        this.onboardingCandidates = await resellerApi.onboardingCandidates(accountId)
      } catch (error) {
        this.onboardingCandidates = null
        this.onboardingError = errorMessage(error)
      } finally {
        this.candidatesLoading = false
      }
    },

    async onboardDescendant(accountId: string, input: DescendantOnboardingInput): Promise<boolean> {
      this.onboarding = true
      this.onboardingError = null
      this.fieldErrors = {}

      try {
        const result = await resellerApi.onboardDescendant(accountId, input)
        this.hierarchy = result.hierarchy
        this.onboardingCandidates = null
        return true
      } catch (error) {
        this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
        this.onboardingError =
          Object.keys(this.fieldErrors).length === 0 ? errorMessage(error) : null
        return false
      } finally {
        this.onboarding = false
      }
    },
  },
})
