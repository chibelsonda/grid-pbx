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
  await callDialog.getByRole('link', { name: /Support call recording/ }).click()

  await expect(page).toHaveURL(new RegExp(`/recordings\\?recording=${recording.id}$`))
  const recordingDialog = page.getByRole('dialog', { name: recording.name })
  await expect(recordingDialog.getByText('No streamable audio is available.')).toBeVisible()
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
