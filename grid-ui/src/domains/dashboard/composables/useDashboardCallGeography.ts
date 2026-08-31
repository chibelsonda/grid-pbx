import axios from 'axios'
import { ref } from 'vue'
import { dashboardApi } from '../api/dashboardApi'
import type { CallActivityRange } from '../schemas/callActivityTrendSchema'
import type { CallGeography } from '../schemas/callGeographySchema'

export function useDashboardCallGeography() {
  const geography = ref<CallGeography | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestSequence = 0

  function reset(): void {
    requestSequence += 1
    geography.value = null
    loading.value = false
    error.value = null
  }

  async function load(accountId: string, range: CallActivityRange): Promise<void> {
    const request = ++requestSequence
    loading.value = true
    error.value = null

    try {
      const result = await dashboardApi.callGeography(accountId, range)
      if (request === requestSequence) geography.value = result
    } catch (caught) {
      if (request !== requestSequence) return
      error.value = axios.isAxiosError(caught)
        ? (caught.response?.data?.message ?? 'Unable to load call geography.')
        : 'Unable to load call geography.'
    } finally {
      if (request === requestSequence) loading.value = false
    }
  }

  return { geography, loading, error, load, reset }
}
