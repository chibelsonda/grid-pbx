import { describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '../api/dashboardApi'
import { callGeographySchema } from '../schemas/callGeographySchema'
import { useDashboardCallGeography } from './useDashboardCallGeography'

vi.mock('../api/dashboardApi', () => ({ dashboardApi: { callGeography: vi.fn() } }))

const geography = callGeographySchema.parse({
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: null,
  range: '7d',
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  status: 'unavailable',
  capability: {
    available: false,
    source: null,
    reason: 'An approved source is required.',
  },
  coverage: { total_calls: 0, located_calls: 0, percentage: 0 },
  locations: [],
  disclosure: 'Estimated geography is not a live location.',
})

describe('useDashboardCallGeography', () => {
  it('loads a bounded range and resets page-local state', async () => {
    vi.mocked(dashboardApi.callGeography).mockResolvedValue(geography)
    const dashboardGeography = useDashboardCallGeography()

    await dashboardGeography.load('6dd4ec45-b29c-4f8b-a142-e886978d1757', '7d')

    expect(dashboardApi.callGeography).toHaveBeenCalledWith(
      '6dd4ec45-b29c-4f8b-a142-e886978d1757',
      '7d',
    )
    expect(dashboardGeography.geography.value).toEqual(geography)
    expect(dashboardGeography.loading.value).toBe(false)

    dashboardGeography.reset()
    expect(dashboardGeography.geography.value).toBeNull()
    expect(dashboardGeography.error.value).toBeNull()
  })
})
