import { computed } from 'vue'
import { useRoute } from 'vue-router'

export function useGlobalSearchListQuery() {
  const route = useRoute()

  return computed(() => {
    const value = route.query.search

    return typeof value === 'string' ? value.trim() : ''
  })
}
