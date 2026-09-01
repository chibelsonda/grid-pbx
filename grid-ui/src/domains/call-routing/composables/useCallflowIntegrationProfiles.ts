import axios from 'axios'
import { ref } from 'vue'
import { callflowIntegrationProfileApi } from '../api/callflowIntegrationProfileApi'
import type {
  CallflowIntegrationProfile,
  CallflowIntegrationProfileInput,
} from '../types/callflowIntegrationProfile'
import { announceCallflowCapabilitiesChanged } from '../services/callflowCapabilityRefresh'

function errorMessage(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

export function useCallflowIntegrationProfiles() {
  const profiles = ref<CallflowIntegrationProfile[]>([])
  const loading = ref(false)
  const saving = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  function clearErrors(): void {
    error.value = null
    fieldErrors.value = {}
  }

  async function load(accountId: string): Promise<void> {
    loading.value = true
    clearErrors()
    try {
      profiles.value = await callflowIntegrationProfileApi.list(accountId)
    } catch (cause) {
      error.value = errorMessage(cause, 'Unable to load callflow integration profiles.')
    } finally {
      loading.value = false
    }
  }

  async function create(accountId: string, input: CallflowIntegrationProfileInput): Promise<boolean> {
    saving.value = true
    clearErrors()
    try {
      profiles.value.push(await callflowIntegrationProfileApi.create(accountId, input))
      profiles.value.sort((left, right) => left.name.localeCompare(right.name))
      announceCallflowCapabilitiesChanged(accountId)
      return true
    } catch (cause) {
      fieldErrors.value = axios.isAxiosError(cause) ? (cause.response?.data?.errors ?? {}) : {}
      error.value = Object.keys(fieldErrors.value).length
        ? null
        : errorMessage(cause, 'Unable to create the integration profile.')
      return false
    } finally {
      saving.value = false
    }
  }

  async function replace(
    accountId: string,
    profileId: string,
    input: CallflowIntegrationProfileInput,
  ): Promise<boolean> {
    saving.value = true
    clearErrors()
    try {
      const updated = await callflowIntegrationProfileApi.update(accountId, profileId, input)
      profiles.value = profiles.value.map((profile) =>
        profile.id === updated.id ? updated : profile,
      )
      announceCallflowCapabilitiesChanged(accountId)
      return true
    } catch (cause) {
      fieldErrors.value = axios.isAxiosError(cause) ? (cause.response?.data?.errors ?? {}) : {}
      error.value = Object.keys(fieldErrors.value).length
        ? null
        : errorMessage(cause, 'Unable to replace the private integration configuration.')
      return false
    } finally {
      saving.value = false
    }
  }

  async function setActive(
    accountId: string,
    profile: CallflowIntegrationProfile,
    isActive: boolean,
  ): Promise<boolean> {
    saving.value = true
    clearErrors()
    try {
      const updated = await callflowIntegrationProfileApi.update(accountId, profile.id, {
        is_active: isActive,
      })
      profiles.value = profiles.value.map((candidate) =>
        candidate.id === updated.id ? updated : candidate,
      )
      announceCallflowCapabilitiesChanged(accountId)
      return true
    } catch (cause) {
      error.value = errorMessage(cause, 'Unable to change the integration profile status.')
      return false
    } finally {
      saving.value = false
    }
  }

  async function remove(accountId: string, profileId: string): Promise<boolean> {
    saving.value = true
    clearErrors()
    try {
      await callflowIntegrationProfileApi.remove(accountId, profileId)
      profiles.value = profiles.value.filter((profile) => profile.id !== profileId)
      announceCallflowCapabilitiesChanged(accountId)
      return true
    } catch (cause) {
      error.value = errorMessage(cause, 'Unable to remove the integration profile.')
      return false
    } finally {
      saving.value = false
    }
  }

  return {
    clearErrors,
    create,
    error,
    fieldErrors,
    load,
    loading,
    profiles,
    remove,
    replace,
    saving,
    setActive,
  }
}
