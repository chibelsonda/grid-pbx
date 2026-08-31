import { describe, expect, it, vi } from 'vitest'
import { dashboardApi } from '../api/dashboardApi'
import { dashboardOverviewSchema } from '../schemas/dashboardOverviewSchema'
import { useDashboardOverview } from './useDashboardOverview'

vi.mock('../api/dashboardApi', () => ({ dashboardApi: { overview: vi.fn() } }))

const overview = dashboardOverviewSchema.parse({
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: null,
  is_stale: true,
  account: {
    id: '6dd4ec45-b29c-4f8b-a142-e886978d1757',
    name: 'GridPBX',
    timezone: 'UTC',
    sync_status: 'stale',
    last_synced_at: null,
  },
  synchronization: {
    status: 'not_started',
    last_successful_at: null,
    active_runs: 0,
    checkpoints: { total: 0, healthy: 0, syncing: 0, stale: 0, error: 0 },
    resources_requiring_attention: [],
    recent_runs: [],
  },
  inventory: {
    extensions: { total: 0, enabled: 0, disabled: 0 },
    devices: {
      total: 0,
      enabled: 0,
      disabled: 0,
      registered: 0,
      unregistered: 0,
      enabled_unregistered: 0,
      unknown_registration: 0,
    },
    phone_numbers: { total: 0, assigned: 0, unassigned: 0 },
    callflows: { total: 0, healthy: 0, attention: 0 },
    voicemail: { boxes: 0, new_messages: 0 },
    queues: { total: 0 },
  },
  calls_today: {
    total: 0,
    inbound: 0,
    outbound: 0,
    answered: 0,
    missed: 0,
    answer_rate: 0,
    average_duration_seconds: 0,
  },
  attention: {
    total: 1,
    items: [
      {
        code: 'synchronization_not_started',
        severity: 'info',
        label: 'Synchronization not started',
        count: 1,
        message: 'No resource synchronization checkpoint is available yet.',
        guidance: 'Run the initial resource synchronizations.',
        resource: 'system-status',
      },
    ],
  },
})

describe('useDashboardOverview', () => {
  it('loads and resets page-local dashboard state', async () => {
    vi.mocked(dashboardApi.overview).mockResolvedValue(overview)
    const dashboard = useDashboardOverview()

    await dashboard.load(overview.account.id)

    expect(dashboardApi.overview).toHaveBeenCalledWith(overview.account.id)
    expect(dashboard.overview.value).toEqual(overview)
    expect(dashboard.loading.value).toBe(false)

    dashboard.reset()
    expect(dashboard.overview.value).toBeNull()
    expect(dashboard.error.value).toBeNull()
  })
})
