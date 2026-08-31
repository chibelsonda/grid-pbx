import axios from 'axios'
import { ref } from 'vue'
import { dashboardApi } from '../api/dashboardApi'
import type { CallActivityRange } from '../schemas/callActivityTrendSchema'
import type { CallQuality } from '../schemas/callQualitySchema'

export function useDashboardCallQuality() {
  const quality = ref<CallQuality | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let requestSequence = 0

  function reset(): void {
    requestSequence += 1
    quality.value = null
    loading.value = false
    error.value = null
  }

  async function load(accountId: string, range: CallActivityRange): Promise<void> {
    const request = ++requestSequence
    loading.value = true
    error.value = null

    try {
      const result = await dashboardApi.callQuality(accountId, range)
      if (request === requestSequence) quality.value = result
    } catch (caught) {
      if (request !== requestSequence) return
      error.value = axios.isAxiosError(caught)
        ? (caught.response?.data?.message ?? 'Unable to load call-quality indicators.')
        : 'Unable to load call-quality indicators.'
    } finally {
      if (request === requestSequence) loading.value = false
    }
  }

  return { quality, loading, error, load, reset }
}
