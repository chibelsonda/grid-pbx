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

test('keeps Blacklist validation inline with shared invalid control styling', async ({ page }) => {
  const issues = collectPageIssues(page)
  await page.route('**/api/v1/accounts/*/blacklists?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
      }),
    })
  })

  await page.goto('/blacklists')
  await expect(page.getByRole('heading', { name: 'Blacklists', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'New blacklist' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create blacklist' })
  await dialog.getByLabel('Blocked caller numbers').fill('555-0100')
  await dialog.getByRole('button', { name: 'Save blacklist' }).click()

  const name = dialog.getByLabel('Blacklist name')
  const numbers = dialog.getByLabel('Blocked caller numbers')
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(numbers).toHaveAttribute('aria-invalid', 'true')
  await expect(numbers).toHaveClass(/border-red-400/)
  await expect(dialog.getByText('Enter a blacklist name.')).toBeVisible()
  await expect(dialog.getByText('Use E.164 format for: 555-0100')).toBeVisible()
  await expect(dialog.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})
