import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/shared/api/http'
import { dashboardApi } from './dashboardApi'
import type { CallActivityTrend } from '../schemas/callActivityTrendSchema'
import type { CallGeography } from '../schemas/callGeographySchema'
import type { CallQuality } from '../schemas/callQualitySchema'
import type { DashboardOverview } from '../schemas/dashboardOverviewSchema'
import type { RecentMissedCalls } from '../schemas/recentMissedCallsSchema'
import type { TopCallDestinations } from '../schemas/topCallDestinationsSchema'

const overview: DashboardOverview = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  is_stale: false,
  account: {
    id: '6dd4ec45-b29c-4f8b-a142-e886978d1757',
    name: 'GridPBX',
    timezone: 'America/Los_Angeles',
    sync_status: 'healthy',
    last_synced_at: '2026-08-31T11:55:00+00:00',
  },
  synchronization: {
    status: 'healthy',
    last_successful_at: '2026-08-31T11:55:00+00:00',
    active_runs: 0,
    checkpoints: { total: 2, healthy: 2, syncing: 0, stale: 0, error: 0 },
    resources_requiring_attention: [],
    recent_runs: [],
  },
  inventory: {
    extensions: { total: 4, enabled: 4, disabled: 0 },
    devices: {
      total: 4,
      enabled: 4,
      disabled: 0,
      registered: 3,
      unregistered: 0,
      enabled_unregistered: 0,
      unknown_registration: 1,
    },
    phone_numbers: { total: 2, assigned: 2, unassigned: 0 },
    callflows: { total: 4, healthy: 4, attention: 0 },
    voicemail: { boxes: 4, new_messages: 1 },
    queues: { total: 1 },
  },
  calls_today: {
    total: 12,
    inbound: 7,
    outbound: 5,
    answered: 10,
    missed: 2,
    answer_rate: 83.3,
    average_duration_seconds: 95,
  },
  attention: { total: 0, items: [] },
}

const activity: CallActivityTrend = {
  range: '7d',
  granularity: 'day',
  timezone: 'America/Los_Angeles',
  from: '2026-08-25T00:00:00-07:00',
  to: '2026-09-01T00:00:00-07:00',
  totals: {
    total: 3,
    inbound: 2,
    outbound: 1,
    answered: 2,
    missed: 1,
    answer_rate: 66.7,
    average_duration_seconds: 45,
  },
  series: [
    {
      start_at: '2026-08-25T00:00:00-07:00',
      end_at: '2026-08-26T00:00:00-07:00',
      total: 3,
      inbound: 2,
      outbound: 1,
      answered: 2,
      missed: 1,
    },
  ],
}

const geography: CallGeography = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  range: '7d',
  timezone: 'America/Los_Angeles',
  from: '2026-08-25T00:00:00-07:00',
  to: '2026-09-01T00:00:00-07:00',
  status: 'ready',
  capability: { available: true, source: 'approved-source', reason: null },
  coverage: { total_calls: 4, located_calls: 3, percentage: 75 },
  locations: [
    {
      key: 'us-wa-seattle',
      label: 'Seattle, WA, US',
      locality: 'Seattle',
      region_code: 'WA',
      country_code: 'US',
      latitude: 47.6062,
      longitude: -122.3321,
      precision: 'numbering_plan',
      total: 3,
      inbound: 2,
      outbound: 1,
    },
  ],
  disclosure: 'Estimated numbering-plan geography, not a live location.',
}

const quality: CallQuality = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  range: '7d',
  timezone: 'America/Los_Angeles',
  from: '2026-08-25T00:00:00-07:00',
  to: '2026-09-01T00:00:00-07:00',
  answer_time: {
    answered_inbound_calls: 3,
    average_pre_answer_seconds: 13,
    disclosure: 'Derived from projected durations.',
  },
  potential_abandonment: {
    threshold_seconds: 15,
    inbound_calls: 6,
    unanswered_inbound_calls: 3,
    potential_calls: 2,
    rate: 33.3,
    disclosure: 'Heuristic only.',
  },
  duration_distribution: {
    total_calls: 7,
    bands: [
      ['under_30', 'Under 30 sec', 0, 29, 3, 42.9],
      ['30_to_59', '30–59 sec', 30, 59, 2, 28.6],
      ['1_to_5_minutes', '1–5 min', 60, 299, 1, 14.3],
      ['5_to_15_minutes', '5–15 min', 300, 899, 1, 14.3],
      ['15_minutes_plus', '15+ min', 900, null, 0, 0],
    ].map(([key, label, minimum_seconds, maximum_seconds, count, percentage]) => ({
      key,
      label,
      minimum_seconds,
      maximum_seconds,
      count,
      percentage,
    })) as CallQuality['duration_distribution']['bands'],
  },
}

const missedCalls: RecentMissedCalls = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  range: '7d',
  timezone: 'America/Los_Angeles',
  from: '2026-08-25T00:00:00-07:00',
  to: '2026-09-01T00:00:00-07:00',
  total: 1,
  items: [
    {
      id: '5b678ad8-49c5-4cab-8622-aee696563723',
      started_at: '2026-08-31T10:00:00+00:00',
      caller: { name: 'Alice Caller', number: '+14155550100' },
      destination: { name: 'Support', number: '1001' },
      duration_seconds: 18,
      hangup_cause: 'NO_ANSWER',
    },
  ],
}

const topDestinations: TopCallDestinations = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  range: '7d',
  timezone: 'America/Los_Angeles',
  from: '2026-08-25T00:00:00-07:00',
  to: '2026-09-01T00:00:00-07:00',
  destinations: [
    {
      name: 'Support',
      number: '1001',
      total: 5,
      inbound: 3,
      outbound: 2,
      answered: 4,
      unanswered: 1,
    },
  ],
}

describe('dashboard API', () => {
  afterEach(() => vi.restoreAllMocks())

  it('loads and validates the account-scoped overview', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: overview } })

    await expect(dashboardApi.overview(overview.account.id)).resolves.toEqual(overview)
    expect(get).toHaveBeenCalledWith(`/api/v1/accounts/${overview.account.id}/dashboard`)
  })

  it('rejects unexpected private projection fields', async () => {
    vi.spyOn(http, 'get').mockResolvedValue({
      data: {
        data: {
          ...overview,
          account: { ...overview.account, switch_account_id: 'private-switch-id' },
        },
      },
    })

    await expect(dashboardApi.overview(overview.account.id)).rejects.toThrow()
  })

  it('loads and validates an account-scoped call activity range', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: activity } })

    await expect(dashboardApi.callActivity(overview.account.id, '7d')).resolves.toEqual(activity)
    expect(get).toHaveBeenCalledWith(
      `/api/v1/accounts/${overview.account.id}/dashboard/call-activity`,
      { params: { range: '7d' } },
    )
  })

  it('loads and validates capability-gated call geography', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: geography } })

    await expect(dashboardApi.callGeography(overview.account.id, '7d')).resolves.toEqual(geography)
    expect(get).toHaveBeenCalledWith(
      `/api/v1/accounts/${overview.account.id}/dashboard/call-geography`,
      { params: { range: '7d' } },
    )
  })

  it('loads and validates call-quality indicators', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: quality } })

    await expect(dashboardApi.callQuality(overview.account.id, '7d')).resolves.toEqual(quality)
    expect(get).toHaveBeenCalledWith(
      `/api/v1/accounts/${overview.account.id}/dashboard/call-quality`,
      { params: { range: '7d' } },
    )
  })

  it('loads and validates bounded recent missed calls', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: missedCalls } })

    await expect(dashboardApi.recentMissedCalls(overview.account.id, '7d')).resolves.toEqual(
      missedCalls,
    )
    expect(get).toHaveBeenCalledWith(
      `/api/v1/accounts/${overview.account.id}/dashboard/recent-missed-calls`,
      { params: { range: '7d' } },
    )
  })

  it('loads and validates bounded top call destinations', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: topDestinations } })

    await expect(dashboardApi.topDestinations(overview.account.id, '7d')).resolves.toEqual(
      topDestinations,
    )
    expect(get).toHaveBeenCalledWith(
      `/api/v1/accounts/${overview.account.id}/dashboard/top-destinations`,
      { params: { range: '7d' } },
    )
  })
})
