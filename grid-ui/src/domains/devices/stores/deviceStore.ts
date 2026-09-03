import axios from 'axios'
import { defineStore } from 'pinia'
import { useUiStore } from '@/app/stores/uiStore'
import { deviceApi } from '../api/deviceApi'
import type { DeviceProvisioningCommand } from '../api/deviceApi'
import type {
  Device,
  DeviceCallerIdNumberOption,
  DeviceHotdeskMemberships,
  DeviceInput,
  DeviceMetaflowResources,
  DeviceProvisioningCatalog,
  DeviceProvisioningEnrollment,
  DeviceRestrictionOption,
  DeviceSchemaCompatibility,
  ExtensionOption,
  SyncState,
} from '../types/device'
import { legacyDeviceSchemaCompatibility } from '../deviceForm'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }
const defaultProvisioningEnrollment: DeviceProvisioningEnrollment = {
  status: 'not_enrolled',
  provider: null,
  eligible: false,
  adapter_available: false,
  can_enroll: false,
  can_detach: false,
  reason: 'Provisioning enrollment state has not been loaded.',
  enrolled_at: null,
  detached_at: null,
}

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
    operationMessage: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    extensionOptions: [] as ExtensionOption[],
    mediaOptions: [] as Array<{ id: string; name: string | null }>,
    metaflowResources: { callflows: [], devices: [] } as DeviceMetaflowResources,
    callerIdNumberOptions: [] as DeviceCallerIdNumberOption[],
    provisioningCatalog: {
      available: false,
      reason: 'Provisioning catalog has not been loaded.',
      brands: [],
    } as DeviceProvisioningCatalog,
    restrictionOptions: [] as DeviceRestrictionOption[],
    schemaCompatibility: structuredClone(
      legacyDeviceSchemaCompatibility,
    ) as DeviceSchemaCompatibility,
    hotdeskMemberships: { users: [], unresolved_count: 0 } as DeviceHotdeskMemberships,
    hotdeskLoading: false,
    hotdeskError: null as string | null,
    provisioningEnrollment: { ...defaultProvisioningEnrollment },
    provisioningEnrollmentLoading: false,
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
      this.operationMessage = null
      this.fieldErrors = {}
      this.extensionOptions = []
      this.mediaOptions = []
      this.metaflowResources = { callflows: [], devices: [] }
      this.callerIdNumberOptions = []
      this.provisioningCatalog = {
        available: false,
        reason: 'Provisioning catalog has not been loaded.',
        brands: [],
      }
      this.restrictionOptions = []
      this.schemaCompatibility = structuredClone(legacyDeviceSchemaCompatibility)
      this.hotdeskMemberships = { users: [], unresolved_count: 0 }
      this.hotdeskLoading = false
      this.hotdeskError = null
      this.provisioningEnrollment = { ...defaultProvisioningEnrollment }
      this.provisioningEnrollmentLoading = false
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
      this.mutationError = null
      this.operationMessage = null
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
    async loadOptions(accountId: string): Promise<void> {
      try {
        const options = await deviceApi.options(accountId)
        this.extensionOptions = options.extensions
        this.mediaOptions = options.media
        this.metaflowResources = options.metaflow_resources ?? { callflows: [], devices: [] }
        this.callerIdNumberOptions = options.caller_id_numbers
        this.provisioningCatalog = options.provisioning_catalog
        this.schemaCompatibility = options.device_schema
        this.restrictionOptions = options.restrictions
      } catch {
        this.extensionOptions = []
        this.mediaOptions = []
        this.metaflowResources = { callflows: [], devices: [] }
        this.callerIdNumberOptions = []
        this.provisioningCatalog = {
          available: false,
          reason: 'Provisioning catalog could not be loaded.',
          brands: [],
        }
        this.restrictionOptions = []
        this.schemaCompatibility = structuredClone(legacyDeviceSchemaCompatibility)
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
    async syncProvisioning(
      accountId: string,
      deviceId: string,
      command: DeviceProvisioningCommand,
    ): Promise<boolean> {
      this.mutationLoading = true
      this.mutationError = null
      this.operationMessage = null

      try {
        const result = await deviceApi.syncProvisioning(accountId, deviceId, command)
        this.operationMessage = result.message
        useUiStore().notify({
          title: command === 'reprovision' ? 'Reprovision requested' : 'Check-sync sent',
          message: result.message,
          tone: 'success',
        })

        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to send the provisioning command.')
        useUiStore().notify({
          title: 'Device command failed',
          message: this.mutationError ?? 'Unable to send the provisioning command.',
          tone: 'error',
        })

        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async loadProvisioningEnrollment(accountId: string, deviceId: string): Promise<void> {
      this.provisioningEnrollmentLoading = true

      try {
        this.provisioningEnrollment = await deviceApi.provisioningEnrollment(accountId, deviceId)
      } catch (error) {
        this.provisioningEnrollment = {
          ...defaultProvisioningEnrollment,
          reason: axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Unable to load provisioning enrollment state.')
            : 'Unable to load provisioning enrollment state.',
        }
      } finally {
        this.provisioningEnrollmentLoading = false
      }
    },
    async enrollProvisioning(accountId: string, deviceId: string): Promise<boolean> {
      return this.mutateProvisioningEnrollment(() =>
        deviceApi.enrollProvisioning(accountId, deviceId),
      )
    },
    async detachProvisioning(accountId: string, deviceId: string): Promise<boolean> {
      return this.mutateProvisioningEnrollment(() =>
        deviceApi.detachProvisioning(accountId, deviceId),
      )
    },
    async mutateProvisioningEnrollment(
      operation: () => Promise<{
        message: string
        enrollment: DeviceProvisioningEnrollment
      }>,
    ): Promise<boolean> {
      this.mutationLoading = true
      this.mutationError = null
      this.operationMessage = null

      try {
        const result = await operation()
        this.provisioningEnrollment = result.enrollment
        this.operationMessage = result.message
        useUiStore().notify({
          title: 'Provisioning updated',
          message: result.message,
          tone: 'success',
        })

        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to update provisioning enrollment.')
        useUiStore().notify({
          title: 'Provisioning update failed',
          message: this.mutationError ?? 'Unable to update provisioning enrollment.',
          tone: 'error',
        })

        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async loadHotdeskUsers(accountId: string, deviceId: string): Promise<void> {
      this.hotdeskLoading = true
      this.hotdeskError = null

      try {
        this.hotdeskMemberships = await deviceApi.hotdeskUsers(accountId, deviceId)
      } catch (error) {
        this.hotdeskError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load active hotdesk users.')
          : 'Unable to load active hotdesk users.'
      } finally {
        this.hotdeskLoading = false
      }
    },
    async signInHotdeskUser(
      accountId: string,
      deviceId: string,
      extensionId: string,
    ): Promise<boolean> {
      return this.mutateHotdesk(() => deviceApi.signInHotdeskUser(accountId, deviceId, extensionId))
    },
    async signOutHotdeskUser(
      accountId: string,
      deviceId: string,
      extensionId: string,
    ): Promise<boolean> {
      return this.mutateHotdesk(() =>
        deviceApi.signOutHotdeskUser(accountId, deviceId, extensionId),
      )
    },
    async mutateHotdesk(operation: () => Promise<DeviceHotdeskMemberships>): Promise<boolean> {
      this.hotdeskLoading = true
      this.mutationError = null
      this.operationMessage = null

      try {
        this.hotdeskMemberships = await operation()
        this.operationMessage = 'Hotdesk session updated.'
        useUiStore().notify({
          title: 'Hotdesk updated',
          message: this.operationMessage,
          tone: 'success',
        })

        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to update the hotdesk session.')
        useUiStore().notify({
          title: 'Hotdesk update failed',
          message: this.mutationError ?? 'Unable to update the hotdesk session.',
          tone: 'error',
        })

        return false
      } finally {
        this.hotdeskLoading = false
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
