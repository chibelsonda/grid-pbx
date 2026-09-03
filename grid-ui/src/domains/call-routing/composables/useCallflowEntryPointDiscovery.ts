import { ref, type Ref } from 'vue'
import { normalizeApiError } from '@/shared/api/apiError'
import { phoneNumberApi } from '@/domains/phone-numbers/api/phoneNumberApi'
import { callflowApi } from '../api/callflowApi'
import type {
  CallflowExtensionAvailability,
  CallflowExtensionDirectoryEntry,
} from '../types/callRouting'

const wait = (milliseconds: number): Promise<void> =>
  new Promise((resolve) => window.setTimeout(resolve, milliseconds))

export function useCallflowEntryPointDiscovery(
  accountId: () => string,
  callflowId: () => string | null,
): {
  directory: Ref<CallflowExtensionDirectoryEntry[]>
  suggestedExtension: Ref<string | null>
  availability: Ref<CallflowExtensionAvailability | null>
  loadingDirectory: Ref<boolean>
  checkingAvailability: Ref<boolean>
  refreshingInventory: Ref<boolean>
  discoveryError: Ref<string | null>
  loadDirectory: (search?: string) => Promise<void>
  checkAvailability: (number: string) => Promise<CallflowExtensionAvailability | null>
  clearAvailability: () => void
  refreshInventory: () => Promise<boolean>
  reset: () => void
} {
  const directory = ref<CallflowExtensionDirectoryEntry[]>([])
  const suggestedExtension = ref<string | null>(null)
  const availability = ref<CallflowExtensionAvailability | null>(null)
  const loadingDirectory = ref(false)
  const checkingAvailability = ref(false)
  const refreshingInventory = ref(false)
  const discoveryError = ref<string | null>(null)
  let directoryRequest = 0
  let availabilityRequest = 0

  async function loadDirectory(search = ''): Promise<void> {
    if (!accountId()) return
    const request = ++directoryRequest
    loadingDirectory.value = true
    discoveryError.value = null

    try {
      const result = await callflowApi.extensionDirectory(accountId(), search, callflowId())
      if (request !== directoryRequest) return
      directory.value = result.entries
      suggestedExtension.value = result.suggested_extension
    } catch (error) {
      if (request !== directoryRequest) return
      discoveryError.value = normalizeApiError(error, 'Unable to load existing extensions.').message
    } finally {
      if (request === directoryRequest) loadingDirectory.value = false
    }
  }

  async function checkAvailability(number: string): Promise<CallflowExtensionAvailability | null> {
    if (!accountId()) return null
    const request = ++availabilityRequest
    checkingAvailability.value = true
    discoveryError.value = null

    try {
      const result = await callflowApi.extensionAvailability(accountId(), number, callflowId())
      if (request !== availabilityRequest) return null

      availability.value = result
      suggestedExtension.value = result.suggested_extension

      return result
    } catch (error) {
      if (request !== availabilityRequest) return null
      discoveryError.value = normalizeApiError(error, 'Unable to verify this extension.').message

      return null
    } finally {
      if (request === availabilityRequest) checkingAvailability.value = false
    }
  }

  async function refreshInventory(): Promise<boolean> {
    if (!accountId()) return false
    refreshingInventory.value = true
    discoveryError.value = null

    try {
      const run = await phoneNumberApi.startSync(accountId(), false)

      for (let attempt = 0; attempt < 40; attempt += 1) {
        const status = await phoneNumberApi.syncStatus(accountId(), run.id)

        if (status.status === 'succeeded') return true
        if (status.status === 'failed') {
          throw new Error(status.error_message ?? 'Phone-number inventory synchronization failed.')
        }

        await wait(500)
      }

      throw new Error('Phone-number inventory refresh timed out. Try again in a moment.')
    } catch (error) {
      discoveryError.value = normalizeApiError(
        error,
        'Unable to refresh phone-number inventory.',
      ).message

      return false
    } finally {
      refreshingInventory.value = false
    }
  }

  function clearAvailability(): void {
    availabilityRequest += 1
    availability.value = null
    checkingAvailability.value = false
  }

  function reset(): void {
    directoryRequest += 1
    directory.value = []
    suggestedExtension.value = null
    loadingDirectory.value = false
    clearAvailability()
    discoveryError.value = null
  }

  return {
    directory,
    suggestedExtension,
    availability,
    loadingDirectory,
    checkingAvailability,
    refreshingInventory,
    discoveryError,
    loadDirectory,
    checkAvailability,
    clearAvailability,
    refreshInventory,
    reset,
  }
}
