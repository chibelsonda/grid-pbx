import axios from 'axios'
import { defineStore } from 'pinia'
import { extensionApi } from '../api/extensionApi'
import { defaultExtensionFormOptions } from '../extensionForm'
import type {
  Extension,
  ExtensionCreate,
  ExtensionDeletionPreview,
  ExtensionDetail,
  ExtensionFormOptions,
  ExtensionRecoveryOperation,
  ExtensionUpdate,
  SyncRun,
  SyncState,
} from '../types/extension'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

export const useExtensionStore = defineStore('extensions', {
  state: () => ({
    records: [] as Extension[],
    detail: null as ExtensionDetail | null,
    formOptions: defaultExtensionFormOptions() as ExtensionFormOptions,
    deletionPreview: null as ExtensionDeletionPreview | null,
    recoveryRecords: [] as ExtensionRecoveryOperation[],
    sync: { ...defaultSync },
    currentRun: null as SyncRun | null,
    search: '',
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    detailLoading: false,
    optionsLoading: false,
    previewLoading: false,
    deletionLoading: false,
    recoveryLoading: false,
    recoveryActionLoading: false,
    syncing: false,
    error: null as string | null,
    detailError: null as string | null,
    optionsError: null as string | null,
    previewError: null as string | null,
    deletionError: null as string | null,
    recoveryError: null as string | null,
    recoveryActionError: null as string | null,
    mutationLoading: false,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.formOptions = defaultExtensionFormOptions()
      this.deletionPreview = null
      this.recoveryRecords = []
      this.sync = { ...defaultSync }
      this.currentRun = null
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
      this.detailError = null
      this.optionsError = null
      this.previewError = null
      this.deletionError = null
      this.recoveryError = null
      this.recoveryActionError = null
      this.mutationError = null
      this.fieldErrors = {}
    },
    async loadOptions(accountId: string): Promise<void> {
      this.optionsLoading = true
      this.optionsError = null

      try {
        this.formOptions = await extensionApi.options(accountId)
      } catch (error) {
        this.optionsError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load extension form options.')
          : 'Unable to load extension form options.'
      } finally {
        this.optionsLoading = false
      }
    },
    async create(accountId: string, input: ExtensionCreate): Promise<ExtensionDetail | null> {
      this.mutationLoading = true
      this.mutationError = null
      this.fieldErrors = {}

      try {
        const extension = await extensionApi.create(accountId, input)
        this.detail = extension
        this.records.unshift(extension)
        this.total += 1
        return extension
      } catch (error) {
        if (axios.isAxiosError(error)) {
          this.fieldErrors = error.response?.data?.errors ?? {}
          this.mutationError = Object.keys(this.fieldErrors).length
            ? null
            : (error.response?.data?.message ?? 'Unable to provision the extension.')
        } else {
          this.mutationError = 'Unable to provision the extension.'
        }

        return null
      } finally {
        this.mutationLoading = false
      }
    },
    async update(
      accountId: string,
      extensionId: string,
      input: ExtensionUpdate,
    ): Promise<ExtensionDetail | null> {
      this.mutationLoading = true
      this.mutationError = null
      this.fieldErrors = {}

      try {
        const extension = await extensionApi.update(accountId, extensionId, input)
        this.detail = extension
        const index = this.records.findIndex((record) => record.id === extension.id)
        if (index >= 0) this.records[index] = extension
        return extension
      } catch (error) {
        if (axios.isAxiosError(error)) {
          this.fieldErrors = error.response?.data?.errors ?? {}
          this.mutationError = Object.keys(this.fieldErrors).length
            ? null
            : (error.response?.data?.message ?? 'Unable to update the extension.')
        } else {
          this.mutationError = 'Unable to update the extension.'
        }

        return null
      } finally {
        this.mutationLoading = false
      }
    },
    async loadDeletionPreview(accountId: string, extensionId: string): Promise<void> {
      this.previewLoading = true
      this.previewError = null
      this.deletionPreview = null

      try {
        this.deletionPreview = await extensionApi.deletionPreview(accountId, extensionId)
      } catch (error) {
        this.previewError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to inspect deletion dependencies.')
          : 'Unable to inspect deletion dependencies.'
      } finally {
        this.previewLoading = false
      }
    },
    async remove(accountId: string, extensionId: string, confirmation: string): Promise<boolean> {
      this.deletionLoading = true
      this.deletionError = null
      this.fieldErrors = {}

      try {
        await extensionApi.remove(accountId, extensionId, confirmation)
        this.records = this.records.filter((record) => record.id !== extensionId)
        this.total = Math.max(0, this.total - 1)
        this.detail = null
        this.deletionPreview = null
        return true
      } catch (error) {
        if (axios.isAxiosError(error)) {
          this.deletionError =
            error.response?.data?.message ?? 'Unable to complete the managed deletion.'
          this.fieldErrors = error.response?.data?.errors ?? {}
        } else {
          this.deletionError = 'Unable to complete the managed deletion.'
        }

        return false
      } finally {
        this.deletionLoading = false
      }
    },
    async loadRecoveryQueue(accountId: string): Promise<void> {
      this.recoveryLoading = true
      this.recoveryError = null

      try {
        this.recoveryRecords = await extensionApi.recoveryQueue(accountId)
      } catch (error) {
        this.recoveryError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load extension recovery operations.')
          : 'Unable to load extension recovery operations.'
      } finally {
        this.recoveryLoading = false
      }
    },
    async recover(
      accountId: string,
      operation: ExtensionRecoveryOperation,
      confirmation: string | null = null,
    ): Promise<boolean> {
      this.recoveryActionLoading = true
      this.recoveryActionError = null

      try {
        const recovered = await extensionApi.recover(accountId, operation.id, confirmation)
        this.recoveryRecords = this.recoveryRecords.filter((record) => record.id !== recovered.id)
        if (operation.recovery_action === 'reconcile') await this.load(accountId, 1)
        return true
      } catch (error) {
        this.recoveryActionError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to recover the extension workflow.')
          : 'Unable to recover the extension workflow.'
        return false
      } finally {
        this.recoveryActionLoading = false
      }
    },
    async loadDetail(accountId: string, extensionId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null
      this.detail = null

      try {
        this.detail = await extensionApi.detail(accountId, extensionId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the extension.')
          : 'Unable to load the extension.'
      } finally {
        this.detailLoading = false
      }
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
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load extensions.')
          : 'Unable to load extensions.'
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
            this.error = this.currentRun.error_message ?? 'Switch synchronization failed.'
            return
          }
        }

        this.error = 'The synchronization is still running. Refresh shortly to see the result.'
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to start synchronization.')
          : 'Unable to start synchronization.'
      } finally {
        this.syncing = false
      }
    },
  },
})
