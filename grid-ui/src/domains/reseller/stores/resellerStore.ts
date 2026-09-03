import axios from 'axios'
import { defineStore } from 'pinia'
import { useUiStore } from '@/app/stores/uiStore'
import { serviceApi } from '@/domains/services/api/serviceApi'
import { resellerApi } from '../api/resellerApi'
import type {
  AccountHierarchy,
  DescendantOnboardingCandidates,
  DescendantOnboardingInput,
  ResellerStatus,
} from '../types/reseller'

const errorMessage = (
  error: unknown,
  fallback = 'Unable to load reseller administration information.',
): string => (axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback)

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
    onboardingNotice: null as string | null,
    onboardingNoticeTone: 'success' as 'success' | 'warning',
    syncingDescendantId: null as string | null,
    descendantSyncError: null as string | null,
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
      this.onboardingNotice = null
      this.onboardingNoticeTone = 'success'
      this.syncingDescendantId = null
      this.descendantSyncError = null
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
        const serviceProjectionStarted = result.service_projection.status !== 'not_started'
        this.onboardingNoticeTone = serviceProjectionStarted ? 'success' : 'warning'
        this.onboardingNotice = serviceProjectionStarted
          ? 'Descendant onboarded. Service ownership synchronization has started.'
          : 'Descendant onboarded, but service ownership synchronization could not start. Retry it from Services.'
        if (serviceProjectionStarted) {
          useUiStore().notify({
            title: 'Descendant onboarded',
            message: this.onboardingNotice,
            tone: 'success',
          })
        }
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

    async synchronizeDescendant(
      scopeAccountId: string,
      descendantAccountId: string,
    ): Promise<boolean> {
      this.syncingDescendantId = descendantAccountId
      this.descendantSyncError = null

      try {
        const run = await serviceApi.synchronize(descendantAccountId)

        if (run.status !== 'succeeded') {
          throw new Error('Service synchronization did not finish successfully.')
        }

        await this.load(scopeAccountId)
        return true
      } catch (error) {
        this.descendantSyncError = errorMessage(
          error,
          'Unable to synchronize service ownership for this descendant.',
        )
        return false
      } finally {
        this.syncingDescendantId = null
      }
    },
  },
})
