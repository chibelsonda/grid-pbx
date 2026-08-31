import axios from 'axios'
import { ref } from 'vue'
import { dashboardApi } from '../api/dashboardApi'
import type { CallActivityRange } from '../schemas/callActivityTrendSchema'
import type { RecentMissedCalls } from '../schemas/recentMissedCallsSchema'

export function useDashboardRecentMissedCalls() {
  const missedCalls = ref<RecentMissedCalls | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestSequence = 0

  function reset(): void {
    requestSequence += 1
    missedCalls.value = null
    loading.value = false
    error.value = null
  }

  async function load(accountId: string, range: CallActivityRange): Promise<void> {
    const request = ++requestSequence
    loading.value = true
    error.value = null

    try {
      const result = await dashboardApi.recentMissedCalls(accountId, range)
      if (request === requestSequence) missedCalls.value = result
    } catch (caught) {
      if (request !== requestSequence) return
      error.value = axios.isAxiosError(caught)
        ? (caught.response?.data?.message ?? 'Unable to load recent missed calls.')
        : 'Unable to load recent missed calls.'
    } finally {
      if (request === requestSequence) loading.value = false
    }
  }

  return { missedCalls, loading, error, load, reset }
}
