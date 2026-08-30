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

test('shows only safe read-only presence and parked-call capabilities', async ({ page }) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/operational-status$/.test(new URL(request.url()).pathname)
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })
  const responsePromise = page.waitForResponse((response) =>
    /\/api\/v1\/accounts\/[^/]+\/operational-status$/.test(new URL(response.url()).pathname),
  )

  await page.goto('/system-status')
  await expect(page.getByRole('heading', { name: 'System status' })).toBeVisible()
  const response = await responsePromise
  const payload = (await response.json()) as {
    data: {
      observed_at: string
      presence: {
        subscription_diagnostics_available: boolean
        live_status_available: false
        commands_available: false
      }
      parking: {
        summary_available: boolean
        active_call_count: number | null
        actions_available: false
      }
    }
  }

  expect(response.status()).toBe(200)
  expect(Object.keys(payload.data).sort()).toEqual(['observed_at', 'parking', 'presence'])
  expect(Object.keys(payload.data.presence).sort()).toEqual([
    'commands_available',
    'live_status_available',
    'subscription_diagnostics_available',
  ])
  expect(Object.keys(payload.data.parking).sort()).toEqual([
    'actions_available',
    'active_call_count',
    'summary_available',
  ])
  expect(payload.data.presence.live_status_available).toBe(false)
  expect(payload.data.presence.commands_available).toBe(false)
  expect(payload.data.parking.actions_available).toBe(false)
  expect(JSON.stringify(payload)).not.toMatch(
    /Call-ID|Presence-ID|Switch-URI|contact|subscription_id|switch_account_id/i,
  )

  await expect(page.getByRole('heading', { name: 'Presence' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Parked calls' })).toBeVisible()
  await expect(
    page.getByText('Live presence status and set/reset commands remain capability-gated.'),
  ).toBeVisible()
  await expect(page.getByText(/Only the aggregate count is exposed/)).toBeVisible()
  await expect(
    page.getByText(/Park and retrieve actions require an active phone call/),
  ).toBeVisible()
  await expect(page.getByText(/Observed/)).toBeVisible()
  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
