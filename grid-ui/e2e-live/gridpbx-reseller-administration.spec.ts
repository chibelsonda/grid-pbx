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

test('shows the selected account reseller boundary without mutation controls', async ({ page }) => {
  const issues = collectPageIssues(page)

  const [hierarchyResponse, resellerResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/hierarchy')),
    page.waitForResponse((response) => response.url().endsWith('/reseller')),
    page.goto('/reseller'),
  ])

  expect(hierarchyResponse.ok()).toBe(true)
  expect(resellerResponse.ok()).toBe(true)
  await expect(page.getByRole('heading', { name: 'Reseller administration' })).toBeVisible()
  await expect(page.getByText('Switch account role')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Billing ownership' })).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Protected administration boundary' }),
  ).toBeVisible()
  await expect(page.getByRole('button', { name: /promote|demote/i })).toHaveCount(0)

  const reviewDescendants = page.getByRole('button', { name: 'Review descendants' })
  if (await reviewDescendants.isVisible()) {
    const candidatesResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' && response.url().endsWith('/descendant-onboarding'),
    )
    await reviewDescendants.click()
    const candidatesResponse = await candidatesResponsePromise

    expect(candidatesResponse.ok()).toBe(true)
    const candidates = (await candidatesResponse.json()) as {
      data: { candidates: Array<Record<string, unknown>> }
    }
    for (const candidate of candidates.data.candidates) {
      expect(candidate).not.toHaveProperty('switch_account_id')
      expect(candidate).not.toHaveProperty('account_id')
    }
    await expect(page.getByRole('heading', { name: 'Onboard a descendant' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Target organization' })).toBeVisible()
    await page.getByRole('button', { name: 'Close panel' }).click()
  }

  const horizontalOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )

  expect(horizontalOverflow).toBeLessThanOrEqual(1)
  expect(issues).toEqual([])
})
