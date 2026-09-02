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

test('keeps the Devices page visible behind the create slide-over', async ({ page }) => {
  const issues = collectPageIssues(page)
  const devicesHeading = page.locator('h1').filter({ hasText: /^Devices$/ })

  await page.setViewportSize({ width: 2048, height: 1200 })

  await page.goto('/devices')
  await expect(devicesHeading).toBeVisible()
  await expect(page.getByText('Projected devices', { exact: true })).toBeVisible()

  await page.getByRole('link', { name: 'Create device' }).click()

  await expect(page).toHaveURL(/\/devices\/new$/)
  await expect(page.getByRole('heading', { name: 'Create device', exact: true })).toBeVisible()
  await expect(page.getByTestId('slide-over-panel')).toBeVisible()
  await expect(devicesHeading).toBeVisible()
  await expect(page.getByText('Projected devices', { exact: true })).toBeVisible()

  await page.getByRole('button', { name: 'Close panel' }).click()

  await expect(page).toHaveURL(/\/devices$/)
  await expect(devicesHeading).toBeVisible()
  expect(issues).toEqual([])
})
