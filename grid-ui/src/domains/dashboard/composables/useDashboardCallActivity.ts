import axios from 'axios'
import { ref } from 'vue'
import { dashboardApi } from '../api/dashboardApi'
import type { CallActivityRange, CallActivityTrend } from '../schemas/callActivityTrendSchema'

export function useDashboardCallActivity() {
  const activity = ref<CallActivityTrend | null>(null)
  const range = ref<CallActivityRange>('7d')
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestSequence = 0

  function reset(): void {
    requestSequence += 1
    activity.value = null
    loading.value = false
    error.value = null
  }

  async function load(
    accountId: string,
    nextRange: CallActivityRange = range.value,
  ): Promise<void> {
    const request = ++requestSequence
    if (nextRange !== range.value) activity.value = null
    range.value = nextRange
    loading.value = true
    error.value = null

    try {
      const result = await dashboardApi.callActivity(accountId, nextRange)
      if (request === requestSequence) activity.value = result
    } catch (caught) {
      if (request !== requestSequence) return
      error.value = axios.isAxiosError(caught)
        ? (caught.response?.data?.message ?? 'Unable to load call activity.')
        : 'Unable to load call activity.'
    } finally {
      if (request === requestSequence) loading.value = false
    }
  }

  return { activity, range, loading, error, load, reset }
}
