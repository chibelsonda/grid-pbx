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

test('shows safe account billing reconciliation and sync history', async ({ page }) => {
  const issues = collectPageIssues(page)
  const serviceResponsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'GET' &&
      response.url().includes('/api/v1/accounts/') &&
      response.url().endsWith('/services'),
  )

  await page.goto('/services')
  const serviceResponse = await serviceResponsePromise
  const payload = (await serviceResponse.json()) as { data: Record<string, unknown> | null }

  expect(serviceResponse.ok()).toBe(true)
  expect(JSON.stringify(payload)).not.toContain('switch_account_id')
  expect(JSON.stringify(payload)).not.toContain('error_code')
  expect(JSON.stringify(payload)).not.toContain('SQLSTATE')
  await expect(page.getByRole('heading', { name: 'Services & limits' })).toBeVisible()
  await page.getByRole('button', { name: 'View details' }).click()
  await expect(page.getByRole('heading', { name: 'Billing reconciliation' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Recent synchronization history' })).toBeVisible()
  await expect(page.getByText(/Ledger row count/)).toBeVisible()
  await expect(page.getByText(/Transaction row count/)).toBeVisible()
  await expect(page.getByRole('button', { name: /Charge|Refund|Debit|Credit/i })).toHaveCount(0)

  const panel = page.getByRole('dialog')
  const horizontalOverflow = await panel.evaluate(
    (element) => element.scrollWidth - element.clientWidth,
  )

  expect(horizontalOverflow).toBeLessThanOrEqual(1)
  expect(issues).toEqual([])
})
