import { effectScope, nextTick, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { globalSearchApi } from '../api/globalSearchApi'
import type { GlobalSearchResponse, GlobalSearchType } from '../types/globalSearch'
import { useGlobalSearch } from './useGlobalSearch'

vi.mock('../api/globalSearchApi', () => ({
  globalSearchApi: { search: vi.fn() },
}))

const search = vi.mocked(globalSearchApi.search)
const response: GlobalSearchResponse = {
  query: 'alice',
  groups: [
    {
      type: 'extension',
      label: 'People & Extensions',
      results: [
        {
          id: 'c6eb1509-a90b-451a-ab11-99f9d70d87af',
          type: 'extension',
          title: 'Alice Operator',
          subtitle: '2101',
          matched_field: 'display_name',
        },
      ],
    },
  ],
  total: 1,
}

describe('useGlobalSearch', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    search.mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('debounces searches and ignores queries shorter than two characters', async () => {
    search.mockResolvedValue(response)
    const scope = effectScope()
    const accountId = ref<string | null>('account-1')
    const selectedTypes = ref<GlobalSearchType[]>([])
    const state = scope.run(() => useGlobalSearch(accountId, selectedTypes))!

    state.query.value = 'a'
    await nextTick()
    await vi.advanceTimersByTimeAsync(300)
    expect(search).not.toHaveBeenCalled()

    state.query.value = 'alice'
    await nextTick()
    await vi.advanceTimersByTimeAsync(249)
    expect(search).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(search).toHaveBeenCalledWith('account-1', 'alice', [], expect.any(AbortSignal))
    expect(state.groups.value).toEqual(response.groups)
    scope.stop()
  })

  it('aborts and clears results when the selected account changes', async () => {
    let signal: AbortSignal | undefined
    search.mockImplementation(async (_accountId, _query, _types, requestSignal) => {
      signal = requestSignal
      return response
    })
    const scope = effectScope()
    const accountId = ref<string | null>('account-1')
    const selectedTypes = ref<GlobalSearchType[]>([])
    const state = scope.run(() => useGlobalSearch(accountId, selectedTypes))!

    state.query.value = 'alice'
    await nextTick()
    await vi.advanceTimersByTimeAsync(250)
    expect(state.groups.value).toEqual(response.groups)

    accountId.value = 'account-2'
    await nextTick()
    expect(signal?.aborted).toBe(true)
    expect(state.groups.value).toEqual([])
    scope.stop()
  })

  it('restarts the current search when type filters change', async () => {
    search.mockResolvedValue(response)
    const scope = effectScope()
    const accountId = ref<string | null>('account-1')
    const selectedTypes = ref<GlobalSearchType[]>([])
    const state = scope.run(() => useGlobalSearch(accountId, selectedTypes))!

    state.query.value = 'alice'
    await nextTick()
    await vi.advanceTimersByTimeAsync(250)

    selectedTypes.value = ['extension']
    await nextTick()
    await vi.advanceTimersByTimeAsync(250)

    expect(search).toHaveBeenLastCalledWith(
      'account-1',
      'alice',
      ['extension'],
      expect.any(AbortSignal),
    )
    scope.stop()
  })
})
