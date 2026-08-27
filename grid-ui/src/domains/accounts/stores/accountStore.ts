import { defineStore } from 'pinia'
import { accountApi } from '../api/accountApi'
import type { Account } from '../types/account'

const storageKey = 'gridpbx:selected-account'

export const useAccountStore = defineStore('accounts', {
  state: () => ({
    accounts: [] as Account[],
    selectedId: localStorage.getItem(storageKey),
    loading: false,
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

      if (accountId) localStorage.setItem(storageKey, accountId)
      else localStorage.removeItem(storageKey)
    },
    reset(): void {
      this.accounts = []
      this.select(null)
    },
  },
})
