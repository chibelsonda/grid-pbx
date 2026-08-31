import axios from 'axios'
import { ref } from 'vue'
import { dashboardApi } from '../api/dashboardApi'
import type { CallActivityRange } from '../schemas/callActivityTrendSchema'
import type { TopCallDestinations } from '../schemas/topCallDestinationsSchema'

export function useDashboardTopDestinations() {
  const destinations = ref<TopCallDestinations | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestSequence = 0

  function reset(): void {
    requestSequence += 1
    destinations.value = null
    loading.value = false
    error.value = null
  }

  async function load(accountId: string, range: CallActivityRange): Promise<void> {
    const request = ++requestSequence
    loading.value = true
    error.value = null

    try {
      const result = await dashboardApi.topDestinations(accountId, range)
      if (request === requestSequence) destinations.value = result
    } catch (caught) {
      if (request !== requestSequence) return
      error.value = axios.isAxiosError(caught)
        ? (caught.response?.data?.message ?? 'Unable to load top call destinations.')
        : 'Unable to load top call destinations.'
    } finally {
      if (request === requestSequence) loading.value = false
    }
  }

  return { destinations, loading, error, load, reset }
}
