import { defineStore } from 'pinia'
import { accountApi } from '../api/accountApi'
import axios from 'axios'
import type { Account, AccountDetail, AccountSettingsInput } from '../types/account'

const storageKey = 'gridpbx:selected-account'

export const useAccountStore = defineStore('accounts', {
  state: () => ({
    accounts: [] as Account[],
    detail: null as AccountDetail | null,
    selectedId: localStorage.getItem(storageKey),
    loading: false,
    detailLoading: false,
    detailError: null as string | null,
    saving: false,
    refreshing: false,
    changingStatus: false,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  getters: {
    selected: (state): Account | null =>
      state.accounts.find((account) => account.id === state.selectedId) ?? null,
  },
  actions: {
    async load(): Promise<void> {
      this.loading = true

      try {
        this.accounts = await accountApi.list()
        const selectedStillExists = this.accounts.some((account) => account.id === this.selectedId)

        if (!selectedStillExists) this.select(this.accounts[0]?.id ?? null)
      } finally {
        this.loading = false
      }
    },
    select(accountId: string | null): void {
      this.selectedId = accountId
      this.detail = null
      this.detailError = null
      this.clearMutationError()

      if (accountId) localStorage.setItem(storageKey, accountId)
      else localStorage.removeItem(storageKey)
    },
    reset(): void {
      this.accounts = []
      this.select(null)
    },
    async loadDetail(accountId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null

      try {
        this.detail = await accountApi.detail(accountId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load account details.')
          : 'Unable to load account details.'
      } finally {
        this.detailLoading = false
      }
    },
    clearMutationError(): void {
      this.mutationError = null
      this.fieldErrors = {}
    },
    async updateSettings(accountId: string, input: AccountSettingsInput): Promise<boolean> {
      this.saving = true
      this.clearMutationError()
      try {
        this.detail = await accountApi.update(accountId, input)
        const account = this.accounts.find((candidate) => candidate.id === accountId)
        if (account && this.detail) {
          account.name = this.detail.name
          account.timezone = this.detail.timezone
        }
        return true
      } catch (error) {
        this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
        this.mutationError =
          Object.keys(this.fieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to save account settings.')
              : 'Unable to save account settings.'
        return false
      } finally {
        this.saving = false
      }
    },
    async refreshProjection(accountId: string): Promise<boolean> {
      this.refreshing = true
      this.clearMutationError()
      try {
        this.detail = await accountApi.refresh(accountId)
        return true
      } catch (error) {
        this.mutationError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to refresh account settings.')
          : 'Unable to refresh account settings.'
        return false
      } finally {
        this.refreshing = false
      }
    },
    async updateStatus(
      accountId: string,
      enabled: boolean,
      confirmation: string,
    ): Promise<boolean> {
      this.changingStatus = true
      this.clearMutationError()
      try {
        this.detail = await accountApi.updateStatus(accountId, enabled, confirmation)
        const account = this.accounts.find((candidate) => candidate.id === accountId)
        if (account && this.detail) account.enabled = this.detail.enabled
        return true
      } catch (error) {
        this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
        this.mutationError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to change account status.')
          : 'Unable to change account status.'
        return false
      } finally {
        this.changingStatus = false
      }
    },
  },
})
