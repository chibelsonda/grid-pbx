import { describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '../api/dashboardApi'
import { callQualitySchema } from '../schemas/callQualitySchema'
import { useDashboardCallQuality } from './useDashboardCallQuality'

vi.mock('../api/dashboardApi', () => ({ dashboardApi: { callQuality: vi.fn() } }))

const quality = callQualitySchema.parse({
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: null,
  range: '7d',
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  answer_time: {
    answered_inbound_calls: 0,
    average_pre_answer_seconds: null,
    disclosure: 'Derived.',
  },
  potential_abandonment: {
    threshold_seconds: 15,
    inbound_calls: 0,
    unanswered_inbound_calls: 0,
    potential_calls: 0,
    rate: 0,
    disclosure: 'Heuristic.',
  },
  duration_distribution: {
    total_calls: 0,
    bands: [
      ['under_30', 'Under 30 sec', 0, 29],
      ['30_to_59', '30–59 sec', 30, 59],
      ['1_to_5_minutes', '1–5 min', 60, 299],
      ['5_to_15_minutes', '5–15 min', 300, 899],
      ['15_minutes_plus', '15+ min', 900, null],
    ].map(([key, label, minimum_seconds, maximum_seconds]) => ({
      key,
      label,
      minimum_seconds,
      maximum_seconds,
      count: 0,
      percentage: 0,
    })),
  },
})

describe('useDashboardCallQuality', () => {
  it('loads the selected range and resets page-local state', async () => {
    vi.mocked(dashboardApi.callQuality).mockResolvedValue(quality)
    const metrics = useDashboardCallQuality()

    await metrics.load('6dd4ec45-b29c-4f8b-a142-e886978d1757', '7d')

    expect(dashboardApi.callQuality).toHaveBeenCalledWith(
      '6dd4ec45-b29c-4f8b-a142-e886978d1757',
      '7d',
    )
    expect(metrics.quality.value).toEqual(quality)

    metrics.reset()
    expect(metrics.quality.value).toBeNull()
    expect(metrics.error.value).toBeNull()
  })
})
