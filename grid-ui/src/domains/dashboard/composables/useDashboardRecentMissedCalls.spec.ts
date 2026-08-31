import { describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '../api/dashboardApi'
import { recentMissedCallsSchema } from '../schemas/recentMissedCallsSchema'
import { useDashboardRecentMissedCalls } from './useDashboardRecentMissedCalls'

vi.mock('../api/dashboardApi', () => ({ dashboardApi: { recentMissedCalls: vi.fn() } }))

const missedCalls = recentMissedCallsSchema.parse({
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: null,
  range: '7d',
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  total: 0,
  items: [],
})

describe('useDashboardRecentMissedCalls', () => {
  it('loads the selected range and resets page-local state', async () => {
    vi.mocked(dashboardApi.recentMissedCalls).mockResolvedValue(missedCalls)
    const recent = useDashboardRecentMissedCalls()

    await recent.load('6dd4ec45-b29c-4f8b-a142-e886978d1757', '7d')

    expect(dashboardApi.recentMissedCalls).toHaveBeenCalledWith(
      '6dd4ec45-b29c-4f8b-a142-e886978d1757',
      '7d',
    )
    expect(recent.missedCalls.value).toEqual(missedCalls)
    expect(recent.loading.value).toBe(false)

    recent.reset()
    expect(recent.missedCalls.value).toBeNull()
    expect(recent.error.value).toBeNull()
  })
})
