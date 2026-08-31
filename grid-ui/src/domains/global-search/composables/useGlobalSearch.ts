import axios from 'axios'
import { computed, onScopeDispose, ref, watch, type Ref } from 'vue'
import { globalSearchApi } from '../api/globalSearchApi'
import type { GlobalSearchGroup, GlobalSearchType } from '../types/globalSearch'

const debounceMilliseconds = 250

export function useGlobalSearch(
  accountId: Ref<string | null>,
  selectedTypes: Ref<GlobalSearchType[]>,
) {
  const query = ref('')
  const groups = ref<GlobalSearchGroup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  let controller: AbortController | null = null
  let timer: ReturnType<typeof setTimeout> | null = null
  let requestSequence = 0

  const total = computed(() => groups.value.reduce((sum, group) => sum + group.results.length, 0))
  const ready = computed(() => query.value.trim().length >= 2)

  function cancelPending(): void {
    if (timer) clearTimeout(timer)
    timer = null
    controller?.abort()
    controller = null
  }

  function reset(): void {
    requestSequence += 1
    cancelPending()
    query.value = ''
    groups.value = []
    loading.value = false
    error.value = null
  }

  watch(
    [accountId, query, selectedTypes],
    ([currentAccountId, currentQuery, currentTypes], [previousAccountId]) => {
      requestSequence += 1
      const sequence = requestSequence
      cancelPending()
      error.value = null

      if (currentAccountId !== previousAccountId) groups.value = []

      const normalizedQuery = currentQuery.trim()
      if (!currentAccountId || normalizedQuery.length < 2) {
        groups.value = []
        loading.value = false
        return
      }

      loading.value = true
      timer = setTimeout(async () => {
        controller = new AbortController()

        try {
          const response = await globalSearchApi.search(
            currentAccountId,
            normalizedQuery,
            currentTypes,
            controller.signal,
          )

          if (sequence === requestSequence) groups.value = response.groups
        } catch (searchError) {
          if (axios.isCancel(searchError)) return
          if (sequence !== requestSequence) return

          groups.value = []
          error.value = axios.isAxiosError(searchError)
            ? (searchError.response?.data?.message ?? 'Unable to search this workspace.')
            : 'Unable to search this workspace.'
        } finally {
          if (sequence === requestSequence) loading.value = false
        }
      }, debounceMilliseconds)
    },
  )

  onScopeDispose(cancelPending)

  return { query, groups, total, ready, loading, error, reset }
}
