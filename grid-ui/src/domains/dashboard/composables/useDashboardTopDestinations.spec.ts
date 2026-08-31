import { describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '../api/dashboardApi'
import { topCallDestinationsSchema } from '../schemas/topCallDestinationsSchema'
import { useDashboardTopDestinations } from './useDashboardTopDestinations'

vi.mock('../api/dashboardApi', () => ({ dashboardApi: { topDestinations: vi.fn() } }))

const destinations = topCallDestinationsSchema.parse({
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: null,
  range: '7d',
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  destinations: [],
})

describe('useDashboardTopDestinations', () => {
  it('loads the selected range and resets page-local state', async () => {
    vi.mocked(dashboardApi.topDestinations).mockResolvedValue(destinations)
    const insights = useDashboardTopDestinations()

    await insights.load('6dd4ec45-b29c-4f8b-a142-e886978d1757', '7d')

    expect(dashboardApi.topDestinations).toHaveBeenCalledWith(
      '6dd4ec45-b29c-4f8b-a142-e886978d1757',
      '7d',
    )
    expect(insights.destinations.value).toEqual(destinations)
    expect(insights.loading.value).toBe(false)

    insights.reset()
    expect(insights.destinations.value).toBeNull()
    expect(insights.error.value).toBeNull()
  })
})
