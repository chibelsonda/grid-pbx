import { expect, test, type Page } from '@playwright/test'

function collectPageIssues(page: Page): string[] {
  const issues: string[] = []
  page.on('console', (message) => {
    if (message.type() === 'error') issues.push(`console: ${message.text()}`)
  })
  page.on('pageerror', (error) => issues.push(`page: ${error.message}`))
  page.on('response', (response) => {
    if (response.status() >= 500) issues.push(`response: ${response.status()} ${response.url()}`)
  })

  return issues
}

function collectObjectKeys(value: unknown): string[] {
  if (Array.isArray(value)) return value.flatMap(collectObjectKeys)
  if (!value || typeof value !== 'object') return []

  return Object.entries(value).flatMap(([key, nested]) => [key, ...collectObjectKeys(nested)])
}

const recording = {
  id: '0c4d5589-c3cd-4f51-a068-f916350c8ebf',
  call_id: 'call-activity-1',
  interaction_id: 'interaction-activity-1',
  direction: 'inbound',
  caller: { name: 'Alice Caller', number: '+14155550100' },
  callee: { name: 'Grid Support', number: '1001' },
  from: 'alice@example.test',
  to: '1001@gridpbx.test',
  request: '1001@gridpbx.test',
  started_at: '2026-08-28T04:00:00Z',
  duration_seconds: 75,
  duration_milliseconds: 75_000,
  name: 'Support call recording',
  description: null,
  content_type: 'audio/mpeg',
  content_length: 4096,
  media_source: 'call_recording',
  media_type: 'mp3',
  source_type: 'call',
  origin: null,
  has_audio: false,
  extension: { id: 'extension-public-id', display_name: 'Support Operator', extension: '1001' },
  call_detail_record_id: '5b678ad8-49c5-4cab-8622-aee696563723',
  last_synced_at: '2026-08-28T05:00:00Z',
  sync_status: 'healthy',
}

const call = {
  id: recording.call_detail_record_id,
  call_id: recording.call_id,
  interaction_id: recording.interaction_id,
  direction: 'inbound',
  caller: recording.caller,
  callee: recording.callee,
  from: recording.from,
  to: recording.to,
  request: recording.request,
  started_at: recording.started_at,
  duration_seconds: recording.duration_seconds,
  billing_seconds: 60,
  answered: true,
  hangup_cause: 'NORMAL_CLEARING',
  disposition: 'SUCCESS',
  recording_available: true,
  recordings: [
    {
      id: recording.id,
      name: recording.name,
      duration_seconds: recording.duration_seconds,
      has_audio: recording.has_audio,
    },
  ],
  extension: recording.extension,
  last_synced_at: recording.last_synced_at,
}

function paginated(data: unknown[]): string {
  return JSON.stringify({
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: data.length,
      import_window_days: 31,
      sync: { status: 'healthy', last_successful_at: null, error_message: null },
    },
  })
}

function dashboardOverview(): string {
  return JSON.stringify({
    data: {
      generated_at: '2026-08-31T12:00:00Z',
      data_as_of: '2026-08-31T11:55:00Z',
      is_stale: false,
      account: {
        id: '6dd4ec45-b29c-4f8b-a142-e886978d1757',
        name: 'GridPBX',
        timezone: 'UTC',
        sync_status: 'healthy',
        last_synced_at: '2026-08-31T11:55:00Z',
      },
      synchronization: {
        status: 'healthy',
        last_successful_at: '2026-08-31T11:55:00Z',
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
          unregistered: 1,
          enabled_unregistered: 1,
          unknown_registration: 0,
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
      attention: {
        total: 1,
        items: [
          {
            code: 'unregistered_devices',
            severity: 'warning',
            label: 'Unregistered devices',
            count: 1,
            message: 'Confirmed endpoints are currently not registered.',
            guidance: 'Review SIP credentials, network connectivity, and provisioning state.',
            resource: 'devices',
          },
        ],
      },
    },
  })
}

function dashboardTrend(range: 'today' | '7d' | '30d'): string {
  const count = range === 'today' ? 24 : range === '7d' ? 7 : 30
  const granularity = range === 'today' ? 'hour' : 'day'
  const interval = range === 'today' ? 3_600_000 : 86_400_000
  const start = Date.parse(
    range === 'today'
      ? '2026-08-31T00:00:00Z'
      : range === '7d'
        ? '2026-08-25T00:00:00Z'
        : '2026-08-02T00:00:00Z',
  )
  const switchTimestamp = (timestamp: number) =>
    new Date(timestamp).toISOString().replace('.000Z', '+00:00')
  const series = Array.from({ length: count }, (_, index) => {
    const total = index % 5
    const inbound = Math.ceil(total / 2)
    const outbound = total - inbound
    const answered = Math.max(0, total - 1)

    return {
      start_at: switchTimestamp(start + interval * index),
      end_at: switchTimestamp(start + interval * (index + 1)),
      total,
      inbound,
      outbound,
      answered,
      missed: total - answered,
    }
  })
  const totals = series.reduce(
    (result, point) => ({
      total: result.total + point.total,
      inbound: result.inbound + point.inbound,
      outbound: result.outbound + point.outbound,
      answered: result.answered + point.answered,
      missed: result.missed + point.missed,
    }),
    { total: 0, inbound: 0, outbound: 0, answered: 0, missed: 0 },
  )

  return JSON.stringify({
    data: {
      range,
      granularity,
      timezone: 'UTC',
      from: switchTimestamp(start),
      to: switchTimestamp(start + interval * count),
      totals: {
        ...totals,
        answer_rate: Number(((totals.answered / totals.total) * 100).toFixed(1)),
        average_duration_seconds: 75,
      },
      series,
    },
  })
}

function dashboardGeography(range: 'today' | '7d' | '30d'): string {
  return JSON.stringify({
    data: {
      generated_at: '2026-08-31T12:00:00+00:00',
      data_as_of: '2026-08-31T11:55:00+00:00',
      range,
      timezone: 'UTC',
      from: '2026-08-31T00:00:00+00:00',
      to: '2026-09-01T00:00:00+00:00',
      status: 'ready',
      capability: { available: true, source: 'approved-test-source', reason: null },
      coverage: { total_calls: 5, located_calls: 4, percentage: 80 },
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
          total: 4,
          inbound: 3,
          outbound: 1,
        },
      ],
      disclosure: 'Estimated numbering-plan geography; this is not a live location.',
    },
  })
}

function dashboardMissedCalls(range: 'today' | '7d' | '30d'): string {
  return JSON.stringify({
    data: {
      generated_at: '2026-08-31T12:00:00+00:00',
      data_as_of: '2026-08-31T11:55:00+00:00',
      range,
      timezone: 'UTC',
      from: '2026-08-31T00:00:00+00:00',
      to: '2026-09-01T00:00:00+00:00',
      total: 1,
      items: [
        {
          id: call.id,
          started_at: '2026-08-31T10:00:00+00:00',
          caller: { name: 'Alice Caller', number: '+14155550100' },
          destination: { name: 'Support', number: '1001' },
          duration_seconds: 18,
          hangup_cause: 'NO_ANSWER',
        },
      ],
    },
  })
}

function dashboardTopDestinations(range: 'today' | '7d' | '30d'): string {
  return JSON.stringify({
    data: {
      generated_at: '2026-08-31T12:00:00+00:00',
      data_as_of: '2026-08-31T11:55:00+00:00',
      range,
      timezone: 'UTC',
      from: '2026-08-31T00:00:00+00:00',
      to: '2026-09-01T00:00:00+00:00',
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
    },
  })
}

function dashboardCallQuality(range: 'today' | '7d' | '30d'): string {
  return JSON.stringify({
    data: {
      generated_at: '2026-08-31T12:00:00+00:00',
      data_as_of: '2026-08-31T11:55:00+00:00',
      range,
      timezone: 'UTC',
      from: '2026-08-31T00:00:00+00:00',
      to: '2026-09-01T00:00:00+00:00',
      answer_time: {
        answered_inbound_calls: 3,
        average_pre_answer_seconds: 13,
        disclosure: 'Derived from total duration minus billed duration.',
      },
      potential_abandonment: {
        threshold_seconds: 15,
        inbound_calls: 6,
        unanswered_inbound_calls: 3,
        potential_calls: 2,
        rate: 33.3,
        disclosure: 'Heuristic only; not a definitive queue-abandonment event.',
      },
      duration_distribution: {
        total_calls: 7,
        bands: [
          {
            key: 'under_30',
            label: 'Under 30 sec',
            minimum_seconds: 0,
            maximum_seconds: 29,
            count: 3,
            percentage: 42.9,
          },
          {
            key: '30_to_59',
            label: '30–59 sec',
            minimum_seconds: 30,
            maximum_seconds: 59,
            count: 2,
            percentage: 28.6,
          },
          {
            key: '1_to_5_minutes',
            label: '1–5 min',
            minimum_seconds: 60,
            maximum_seconds: 299,
            count: 1,
            percentage: 14.3,
          },
          {
            key: '5_to_15_minutes',
            label: '5–15 min',
            minimum_seconds: 300,
            maximum_seconds: 899,
            count: 1,
            percentage: 14.3,
          },
          {
            key: '15_minutes_plus',
            label: '15+ min',
            minimum_seconds: 900,
            maximum_seconds: null,
            count: 0,
            percentage: 0,
          },
        ],
      },
    },
  })
}

async function mockDashboardApis(page: Page): Promise<void> {
  await page.route('**/api/v1/accounts/*/dashboard', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: dashboardOverview() }),
  )
  await page.route('**/api/v1/accounts/*/dashboard/call-activity?*', (route) => {
    const requested = new URL(route.request().url()).searchParams.get('range')
    const range = requested === 'today' || requested === '30d' ? requested : '7d'

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: dashboardTrend(range),
    })
  })
  await page.route('**/api/v1/accounts/*/dashboard/call-geography?*', (route) => {
    const requested = new URL(route.request().url()).searchParams.get('range')
    const range = requested === 'today' || requested === '30d' ? requested : '7d'

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: dashboardGeography(range),
    })
  })
  await page.route('**/api/v1/accounts/*/dashboard/recent-missed-calls?*', (route) => {
    const requested = new URL(route.request().url()).searchParams.get('range')
    const range = requested === 'today' || requested === '30d' ? requested : '7d'

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: dashboardMissedCalls(range),
    })
  })
  await page.route('**/api/v1/accounts/*/dashboard/top-destinations?*', (route) => {
    const requested = new URL(route.request().url()).searchParams.get('range')
    const range = requested === 'today' || requested === '30d' ? requested : '7d'

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: dashboardTopDestinations(range),
    })
  })
  await page.route('**/api/v1/accounts/*/dashboard/call-quality?*', (route) => {
    const requested = new URL(route.request().url()).searchParams.get('range')
    const range = requested === 'today' || requested === '30d' ? requested : '7d'

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: dashboardCallQuality(range),
    })
  })
  await page.route('**/api/v1/accounts/*/call-detail-records?*', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: paginated([]) }),
  )
}

test('renders and changes the account-timezone dashboard call trend', async ({ page }) => {
  const issues = collectPageIssues(page)
  await mockDashboardApis(page)

  await page.goto('/')

  const quickActions = page
    .getByRole('heading', { name: 'Quick actions' })
    .locator('xpath=ancestor::section[1]')
  await expect(quickActions).toBeInViewport()
  await expect(quickActions.getByRole('link', { name: 'Create extension' })).toBeVisible()

  const chart = page
    .getByRole('heading', { name: 'Call activity trend' })
    .locator('xpath=ancestor::section[1]')
  await expect(chart.getByRole('button', { name: '7 days', exact: true })).toHaveAttribute(
    'aria-pressed',
    'true',
  )
  await expect(page.getByText('UTC · Started-call buckets')).toBeVisible()
  await expect(page.locator('[title*=" calls, "]')).toHaveCount(7)

  await chart.getByRole('button', { name: 'Today', exact: true }).click()

  await expect(chart.getByRole('button', { name: 'Today', exact: true })).toHaveAttribute(
    'aria-pressed',
    'true',
  )
  await expect(page.locator('[title*=" calls, "]')).toHaveCount(24)
  await expect(page.getByRole('heading', { name: 'Call geography' })).toBeVisible()
  await expect(page.getByLabel('Estimated call geography map')).toBeVisible()
  await expect(page.getByText('Accessible non-map view of the same data')).toBeVisible()
  await expect(page.getByText(/not a live location/)).toBeVisible()
  const missedCalls = page
    .getByRole('heading', { name: 'Recent missed calls' })
    .locator('xpath=ancestor::section[1]')
  await expect(missedCalls.getByText('Alice Caller')).toBeVisible()
  await expect(missedCalls.getByText('+14155550100')).toBeVisible()
  await expect(missedCalls.getByRole('link', { name: /Inspect/ })).toHaveAttribute(
    'href',
    new RegExp(`cdr=${call.id}`),
  )
  const insights = page
    .getByRole('heading', { name: 'Call insights' })
    .locator('xpath=ancestor::section[1]')
  await expect(insights.getByText('Peak calling hour')).toBeVisible()
  await expect(insights.getByText('Support')).toBeVisible()
  await expect(insights.getByRole('link', { name: /Support/ })).toHaveAttribute(
    'href',
    /search=1001/,
  )
  const quality = page
    .getByRole('heading', { name: 'Call quality indicators' })
    .locator('xpath=ancestor::section[1]')
  await expect(quality.getByText('13s')).toBeVisible()
  await expect(quality.getByText('33.3%')).toBeVisible()
  await expect(quality.getByRole('link', { name: /Potential abandonment/ })).toHaveAttribute(
    'href',
    /outcome=unanswered.*duration_max=15/,
  )

  await page.getByLabel('Call history drill-down direction').click()
  await page.getByRole('option', { name: 'Open inbound calls' }).click()
  const drilldownRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())

    return (
      url.pathname.endsWith('/call-detail-records') &&
      url.searchParams.has('started_after') &&
      url.searchParams.has('started_before')
    )
  })
  await page.locator('a[aria-label*="inbound calls in Call History"]').nth(2).click()

  const requestUrl = new URL((await drilldownRequest).url())
  expect(requestUrl.searchParams.get('direction')).toBe('inbound')
  expect(requestUrl.searchParams.get('started_after')).toBe('2026-08-31T02:00:00+00:00')
  expect(requestUrl.searchParams.get('started_before')).toBe('2026-08-31T03:00:00+00:00')
  await expect(page).toHaveURL(
    /\/call-history\?.*started_after=.*started_before=.*direction=inbound/,
  )
  await expect(page.getByText('Dashboard period', { exact: true })).toBeVisible()
  await expect(
    page.getByLabel('Active dashboard call period').getByText('· Inbound', { exact: true }),
  ).toBeVisible()

  const clearedRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())

    return url.pathname.endsWith('/call-detail-records') && !url.searchParams.has('started_after')
  })
  await page.getByRole('button', { name: 'Clear dashboard period' }).click()
  await clearedRequest
  await expect(page).toHaveURL('/call-history')
  await expect(page.getByText('Dashboard period', { exact: true })).toHaveCount(0)

  await page.goto('/')
  const qualityDrilldownRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())

    return (
      url.pathname.endsWith('/call-detail-records') &&
      url.searchParams.get('outcome') === 'unanswered' &&
      url.searchParams.get('duration_max') === '15'
    )
  })
  await page
    .getByRole('heading', { name: 'Call quality indicators' })
    .locator('xpath=ancestor::section[1]')
    .getByRole('link', { name: /Potential abandonment/ })
    .click()

  const qualityRequestUrl = new URL((await qualityDrilldownRequest).url())
  expect(qualityRequestUrl.searchParams.get('direction')).toBe('inbound')
  expect(qualityRequestUrl.searchParams.get('outcome')).toBe('unanswered')
  expect(qualityRequestUrl.searchParams.get('duration_max')).toBe('15')
  await expect(page.getByLabel('Active dashboard call period')).toContainText('Unanswered')
  await expect(page.getByLabel('Active dashboard call period')).toContainText('Duration 0–15s')
  expect(issues).toEqual([])
})

test('renders numeric attention badges as circles', async ({ page }) => {
  const issues = collectPageIssues(page)
  await mockDashboardApis(page)

  await page.goto('/')

  const badge = page.getByLabel('1 Unregistered devices')
  await expect(badge).toBeVisible()
  const bounds = await badge.boundingBox()
  expect(bounds).not.toBeNull()
  expect(bounds!.width).toBe(bounds!.height)
  expect(issues).toEqual([])
})

test('renders the live account Dashboard without private projection identifiers', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const endpointSuffixes = [
    '/dashboard',
    '/dashboard/call-activity',
    '/dashboard/call-geography',
    '/dashboard/call-quality',
    '/dashboard/recent-missed-calls',
    '/dashboard/top-destinations',
  ]
  const responsePromises = endpointSuffixes.map((suffix) =>
    page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' && new URL(response.url()).pathname.endsWith(suffix),
    ),
  )

  await page.goto('/')
  const responses = await Promise.all(responsePromises)
  const payloads = await Promise.all(
    responses.map(async (response) => {
      expect(response.ok()).toBe(true)

      return (await response.json()) as unknown
    }),
  )
  const keys = payloads.flatMap(collectObjectKeys)
  const overview = payloads[0] as { data: { account: { id: string } } }

  expect(keys).not.toContain('switch_account_id')
  expect(keys).not.toContain('switch_resource_id')
  expect(keys).not.toContain('switch_json')
  expect(keys).not.toContain('call_detail_record_id')
  expect(keys).not.toContain('sync_run_id')
  expect(overview.data.account.id).toMatch(
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
  )
  await expect(page.getByRole('heading', { name: 'Call activity trend' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Recent missed calls' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Call insights' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Call quality indicators' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Call geography' })).toBeVisible()
  expect(issues).toEqual([])
})

test('validates call activity filters and follows the safe CDR-recording relationship', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.route('**/api/v1/accounts/*/call-detail-records?*', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: paginated([call]) }),
  )
  await page.route(`**/api/v1/accounts/*/call-detail-records/${call.id}`, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: call }),
    }),
  )
  await page.route('**/api/v1/accounts/*/recordings?*', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: paginated([recording]) }),
  )
  await page.route(`**/api/v1/accounts/*/recordings/${recording.id}`, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: recording }),
    }),
  )

  await page.goto('/call-history')
  await expect(page.getByRole('heading', { name: 'Call History', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Advanced filters' }).click()
  await page.getByLabel('Start date').fill('2026-08-29')
  await page.getByLabel('End date').fill('2026-08-28')
  await page.getByRole('button', { name: 'Apply filters' }).click()
  await expect(page.getByLabel('End date')).toHaveAttribute('aria-invalid', 'true')
  await expect(page.getByLabel('End date')).toHaveClass(/border-red-400/)
  await expect(page.getByText('The end date must be on or after the start date.')).toBeVisible()

  await page.getByRole('button', { name: /View call from/ }).click()
  const callDialog = page.getByRole('dialog', { name: /\+14155550100/ })
  await expect(callDialog.getByText('Related recordings')).toBeVisible()
  await expect(callDialog.getByRole('tab', { name: /basic|advanced/i })).toHaveCount(0)
  await expect(callDialog.getByRole('button', { name: /edit|delete/i })).toHaveCount(0)
  await callDialog.getByRole('link', { name: /Support call recording/ }).click()

  await expect(page).toHaveURL(new RegExp(`/recordings\\?recording=${recording.id}$`))
  const recordingDialog = page.getByRole('dialog', { name: recording.name })
  await expect(recordingDialog.getByText('No streamable audio is available.')).toBeVisible()
  await expect(recordingDialog.getByRole('tab', { name: /basic|advanced/i })).toHaveCount(0)
  await expect(recordingDialog.getByRole('button', { name: /edit|delete/i })).toHaveCount(0)
  await expect(recordingDialog.getByRole('link', { name: 'View linked call' })).toHaveAttribute(
    'href',
    `/call-history?cdr=${call.id}`,
  )
  await recordingDialog.getByRole('button', { name: 'Close panel' }).click()

  await page.getByRole('button', { name: 'Advanced filters' }).click()
  await page.getByLabel('Minimum seconds').fill('120')
  await page.getByLabel('Maximum seconds').fill('60')
  await page.getByRole('button', { name: 'Apply filters' }).click()
  await expect(page.getByLabel('Maximum seconds')).toHaveAttribute('aria-invalid', 'true')
  await expect(page.getByLabel('Maximum seconds')).toHaveClass(/border-red-400/)
  await expect(
    page.getByText('The maximum duration must be greater than or equal to the minimum duration.'),
  ).toBeVisible()
  expect(issues).toEqual([])
})
