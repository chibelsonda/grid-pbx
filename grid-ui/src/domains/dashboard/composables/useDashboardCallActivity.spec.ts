import { describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '../api/dashboardApi'
import { callActivityTrendSchema } from '../schemas/callActivityTrendSchema'
import { useDashboardCallActivity } from './useDashboardCallActivity'

vi.mock('../api/dashboardApi', () => ({ dashboardApi: { callActivity: vi.fn() } }))

const activity = callActivityTrendSchema.parse({
  range: '30d',
  granularity: 'day',
  timezone: 'UTC',
  from: '2026-08-02T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  totals: {
    total: 0,
    inbound: 0,
    outbound: 0,
    answered: 0,
    missed: 0,
    answer_rate: 0,
    average_duration_seconds: 0,
  },
  series: [],
})

describe('useDashboardCallActivity', () => {
  it('loads a selected range and resets page-local state', async () => {
    vi.mocked(dashboardApi.callActivity).mockResolvedValue(activity)
    const dashboardActivity = useDashboardCallActivity()

    await dashboardActivity.load('6dd4ec45-b29c-4f8b-a142-e886978d1757', '30d')

    expect(dashboardApi.callActivity).toHaveBeenCalledWith(
      '6dd4ec45-b29c-4f8b-a142-e886978d1757',
      '30d',
    )
    expect(dashboardActivity.range.value).toBe('30d')
    expect(dashboardActivity.activity.value).toEqual(activity)
    expect(dashboardActivity.loading.value).toBe(false)

    dashboardActivity.reset()
    expect(dashboardActivity.activity.value).toBeNull()
    expect(dashboardActivity.error.value).toBeNull()
  })
})
