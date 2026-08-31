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

test('organizes desktop navigation into route-aware accordion groups', async ({ page }) => {
  const issues = collectPageIssues(page)
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/devices')

  const peopleGroup = page.getByRole('button', { name: 'People & Endpoints', exact: true })
  const routingGroup = page.getByRole('button', { name: 'Numbers & Routing', exact: true })

  await expect(peopleGroup).toHaveAttribute('aria-expanded', 'true')
  await expect(page.getByRole('link', { name: 'Devices', exact: true })).toBeVisible()

  await routingGroup.click()
  await expect(peopleGroup).toHaveAttribute('aria-expanded', 'false')
  await expect(routingGroup).toHaveAttribute('aria-expanded', 'true')
  await expect(page.getByRole('link', { name: 'Phone Numbers', exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Devices', exact: true })).toBeHidden()

  await page.getByRole('link', { name: 'Phone Numbers', exact: true }).click()
  await expect(page).toHaveURL(/\/phone-numbers$/)
  await expect(routingGroup).toHaveAttribute('aria-expanded', 'true')

  await page.getByRole('button', { name: 'Toggle navigation width' }).click()
  const collapsedGroup = page.getByRole('button', {
    name: 'People & Endpoints. Expand navigation to view links.',
  })
  await expect(collapsedGroup).toBeVisible()
  await expect(page.getByRole('link', { name: 'Billing', exact: true })).toBeVisible()
  await collapsedGroup.click()
  await expect(page.getByRole('button', { name: 'People & Endpoints', exact: true })).toBeVisible()
  expect(issues).toEqual([])
})

test('closes mobile navigation after selecting a route', async ({ page }) => {
  const issues = collectPageIssues(page)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/devices')

  await page.getByRole('button', { name: 'Open navigation' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('navigation', { name: 'Primary navigation' })).toBeVisible()

  await dialog.getByRole('link', { name: 'Billing', exact: true }).click()
  await expect(page).toHaveURL(/\/billing$/)
  await expect(dialog).toHaveCount(0)
  expect(issues).toEqual([])
})
