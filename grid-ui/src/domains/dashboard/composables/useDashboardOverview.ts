import axios from 'axios'
import { ref } from 'vue'
import { dashboardApi } from '../api/dashboardApi'
import type { DashboardOverview } from '../schemas/dashboardOverviewSchema'

export function useDashboardOverview() {
  const overview = ref<DashboardOverview | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestSequence = 0

  function reset(): void {
    requestSequence += 1
    overview.value = null
    loading.value = false
    error.value = null
  }

  async function load(accountId: string): Promise<void> {
    const request = ++requestSequence
    loading.value = true
    error.value = null

    try {
      const result = await dashboardApi.overview(accountId)
      if (request === requestSequence) overview.value = result
    } catch (caught) {
      if (request !== requestSequence) return
      error.value = axios.isAxiosError(caught)
        ? (caught.response?.data?.message ?? 'Unable to load the operational dashboard.')
        : 'Unable to load the operational dashboard.'
    } finally {
      if (request === requestSequence) loading.value = false
    }
  }

  return { overview, loading, error, load, reset }
}
