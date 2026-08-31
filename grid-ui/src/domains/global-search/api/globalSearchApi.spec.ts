import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/shared/api/http'
import { globalSearchApi } from './globalSearchApi'

const accountId = '26a82e3b-564f-4ced-83de-0114f6568a2b'
const resultId = 'c6eb1509-a90b-451a-ab11-99f9d70d87af'
const extensionResult = {
  id: resultId,
  type: 'extension' as const,
  title: 'Alice Operator',
  subtitle: '2101 · alice@example.test',
  matched_field: 'display_name',
}
const response = {
  query: 'alice',
  groups: [
    {
      type: 'extension' as const,
      label: 'People & Extensions',
      results: [extensionResult],
    },
  ],
  total: 1,
}

describe('global search API', () => {
  afterEach(() => vi.restoreAllMocks())

  it('loads and validates selected-account projection results', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: response } })
    const controller = new AbortController()

    await expect(
      globalSearchApi.search(accountId, 'alice', ['extension'], controller.signal),
    ).resolves.toEqual(response)
    expect(get).toHaveBeenCalledWith(`/api/v1/accounts/${accountId}/search`, {
      params: { q: 'alice', types: ['extension'] },
      signal: controller.signal,
    })
  })

  it('rejects unexpected private fields', async () => {
    vi.spyOn(http, 'get').mockResolvedValue({
      data: {
        data: {
          ...response,
          groups: [
            {
              ...response.groups[0],
              results: [{ ...extensionResult, switch_resource_id: 'private' }],
            },
          ],
        },
      },
    })

    await expect(globalSearchApi.search(accountId, 'alice', [])).rejects.toThrow()
  })
})
