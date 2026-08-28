import axios from 'axios'
import { defineStore } from 'pinia'
import { deviceApi } from '../api/deviceApi'
import type { Device, DeviceInput, ExtensionOption, SyncState } from '../types/device'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

export const useDeviceStore = defineStore('devices', {
  state: () => ({
    records: [] as Device[],
    detail: null as Device | null,
    sync: { ...defaultSync },
    search: '',
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    detailLoading: false,
    error: null as string | null,
    detailError: null as string | null,
    mutationLoading: false,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    extensionOptions: [] as ExtensionOption[],
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.sync = { ...defaultSync }
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
      this.detailError = null
      this.mutationError = null
      this.fieldErrors = {}
      this.extensionOptions = []
    },
    async load(accountId: string, page?: number): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await deviceApi.list(accountId, this.search, page ?? this.page)
        this.records = response.data
        this.sync = response.meta.sync
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load devices.')
          : 'Unable to load devices.'
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, deviceId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null
      this.detail = null

      try {
        this.detail = await deviceApi.detail(accountId, deviceId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the device.')
          : 'Unable to load the device.'
      } finally {
        this.detailLoading = false
      }
    },
    async loadExtensionOptions(accountId: string): Promise<void> {
      try {
        this.extensionOptions = await deviceApi.extensionOptions(accountId)
      } catch {
        this.extensionOptions = []
      }
    },
    async create(accountId: string, input: DeviceInput): Promise<Device | null> {
      return this.mutate(() => deviceApi.create(accountId, input))
    },
    async update(accountId: string, deviceId: string, input: DeviceInput): Promise<Device | null> {
      return this.mutate(() => deviceApi.update(accountId, deviceId, input))
    },
    async remove(accountId: string, deviceId: string): Promise<boolean> {
      this.mutationLoading = true
      this.mutationError = null

      try {
        await deviceApi.remove(accountId, deviceId)
        this.records = this.records.filter((device) => device.id !== deviceId)
        this.detail = null

        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to delete the device.')

        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async mutate(operation: () => Promise<Device>): Promise<Device | null> {
      this.mutationLoading = true
      this.mutationError = null
      this.fieldErrors = {}

      try {
        const device = await operation()
        this.detail = device
        const index = this.records.findIndex((record) => record.id === device.id)

        if (index === -1) this.records.unshift(device)
        else this.records[index] = device

        return device
      } catch (error) {
        this.captureMutationError(error, 'Unable to save the device.')

        return null
      } finally {
        this.mutationLoading = false
      }
    },
    captureMutationError(error: unknown, fallback: string): void {
      if (axios.isAxiosError(error)) {
        this.mutationError = error.response?.data?.message ?? fallback
        this.fieldErrors = error.response?.data?.errors ?? {}

        return
      }

      this.mutationError = fallback
    },
  },
})
