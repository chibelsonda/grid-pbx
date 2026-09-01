import { defineStore } from 'pinia'
import { accountApi } from '../api/accountApi'
import axios from 'axios'
import type {
  Account,
  AccountDetail,
  AccountSettingsInput,
  AccountSettingsOptions,
  OrganizationBrandingResult,
} from '../types/account'

const storageKey = 'gridpbx:selected-account'

function emptySettingsOptions(): AccountSettingsOptions {
  return {
    restrictions: [],
    callflows: [],
    metaflow_resources: {
      media: [],
      callflows: [],
      devices: [],
      extensions: [],
    },
  }
}

export const useAccountStore = defineStore('accounts', {
  state: () => ({
    accounts: [] as Account[],
    detail: null as AccountDetail | null,
    settingsOptions: emptySettingsOptions(),
    settingsOptionsError: null as string | null,
    selectedId: localStorage.getItem(storageKey),
    loading: false,
    detailLoading: false,
    detailError: null as string | null,
    saving: false,
    refreshing: false,
    changingStatus: false,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    organizationLogoUrl: null as string | null,
    organizationLogoLoading: false,
    organizationLogoSaving: false,
    organizationLogoError: null as string | null,
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
      this.settingsOptions = emptySettingsOptions()
      this.settingsOptionsError = null
      this.detailError = null
      this.clearMutationError()

      if (accountId) localStorage.setItem(storageKey, accountId)
      else localStorage.removeItem(storageKey)
    },
    reset(): void {
      this.accounts = []
      this.select(null)
      this.releaseOrganizationLogo()
    },
    async loadDetail(accountId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null
      this.settingsOptionsError = null

      try {
        const [detailResult, optionsResult] = await Promise.allSettled([
          accountApi.detail(accountId),
          accountApi.settingsOptions(accountId),
        ])

        if (detailResult.status === 'rejected') throw detailResult.reason
        this.detail = detailResult.value

        if (optionsResult.status === 'fulfilled') {
          this.settingsOptions = optionsResult.value
        } else {
          this.settingsOptionsError = 'Live Switch settings choices are temporarily unavailable.'
        }
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
    async loadOrganizationLogo(): Promise<void> {
      const account = this.selected
      this.organizationLogoError = null

      if (!account?.organization.branding?.logo_available) {
        this.releaseOrganizationLogo()
        return
      }

      this.organizationLogoLoading = true
      try {
        const blob = await accountApi.organizationLogo(account.id)
        this.replaceOrganizationLogoUrl(URL.createObjectURL(blob))
      } catch (error) {
        this.releaseOrganizationLogo()
        this.organizationLogoError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the organization logo.')
          : 'Unable to load the organization logo.'
      } finally {
        this.organizationLogoLoading = false
      }
    },
    async uploadOrganizationLogo(file: File): Promise<boolean> {
      const account = this.selected
      if (!account) return false

      this.organizationLogoSaving = true
      this.organizationLogoError = null
      try {
        const branding = await accountApi.uploadOrganizationLogo(account.id, file)
        this.applyOrganizationBranding(branding)
        await this.loadOrganizationLogo()
        return true
      } catch (error) {
        const fieldErrors = axios.isAxiosError(error) ? error.response?.data?.errors : undefined
        this.organizationLogoError =
          fieldErrors?.logo?.[0] ??
          (axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Unable to upload the organization logo.')
            : 'Unable to upload the organization logo.')
        return false
      } finally {
        this.organizationLogoSaving = false
      }
    },
    async removeOrganizationLogo(): Promise<boolean> {
      const account = this.selected
      if (!account) return false

      this.organizationLogoSaving = true
      this.organizationLogoError = null
      try {
        const branding = await accountApi.removeOrganizationLogo(account.id)
        this.applyOrganizationBranding(branding)
        this.releaseOrganizationLogo()
        return true
      } catch (error) {
        this.organizationLogoError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to remove the organization logo.')
          : 'Unable to remove the organization logo.'
        return false
      } finally {
        this.organizationLogoSaving = false
      }
    },
    applyOrganizationBranding(branding: OrganizationBrandingResult): void {
      for (const account of this.accounts) {
        if (account.organization.id !== branding.organization_id) continue
        account.organization.branding = {
          logo_available: branding.logo_available,
          logo_updated_at: branding.logo_updated_at,
        }
      }

      if (this.detail?.organization.id === branding.organization_id) {
        this.detail.organization.branding = {
          logo_available: branding.logo_available,
          logo_updated_at: branding.logo_updated_at,
        }
      }
    },
    replaceOrganizationLogoUrl(url: string | null): void {
      if (this.organizationLogoUrl) URL.revokeObjectURL(this.organizationLogoUrl)
      this.organizationLogoUrl = url
    },
    releaseOrganizationLogo(): void {
      this.replaceOrganizationLogoUrl(null)
    },
  },
})
