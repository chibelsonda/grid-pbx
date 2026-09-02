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

async function expectInsideViewport(
  page: Page,
  locator: ReturnType<Page['locator']>,
): Promise<void> {
  const bounds = await locator.boundingBox()
  expect(bounds).not.toBeNull()
  expect(bounds!.x).toBeGreaterThanOrEqual(0)
  expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390)
}

test('keeps the Blacklist inventory and form accessible on mobile', async ({ page }, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  const record = {
    id: '11111111-1111-4111-8111-111111111111',
    name: 'Blocked callers',
    should_block_anonymous: true,
    is_active: true,
    number_count: 1,
    numbers: [{ id: '22222222-2222-4222-8222-222222222222', number: '+15550001000' }],
    sync_status: 'healthy',
    last_synced_at: null,
  }

  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:blacklists|sync\/blacklists)(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.route('**/api/v1/accounts/*/blacklists?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [record],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
      }),
    })
  })
  await page.route('**/api/v1/accounts/*/blacklists/*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: record }),
    })
  })

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/blacklists')
  await expect(page.getByRole('heading', { name: 'Blacklists', exact: true })).toBeVisible()

  for (const action of [
    page.getByRole('button', { name: 'Sync from Switch', exact: true }),
    page.getByRole('button', { name: 'Create blacklist' }),
    page.getByRole('button', { name: 'Search', exact: true }),
  ]) {
    await expect(action).toBeVisible()
    await expectInsideViewport(page, action)
  }

  const table = page.getByRole('table', { name: 'Blacklists for the selected Switch account' })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(5)
  await expect(table).toHaveAttribute('aria-busy', 'false')

  await table.getByRole('cell', { name: '1', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Edit blacklist' })).toBeVisible()
  await page
    .getByRole('dialog', { name: 'Edit blacklist' })
    .getByRole('button', { name: 'Cancel' })
    .click()
  await expect(page).not.toHaveURL(/(?:\?|&)blacklist=/)

  const recordButton = table.getByRole('button', { name: record.name })
  await recordButton.focus()
  await expect(recordButton).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('heading', { name: 'Edit blacklist' })).toBeVisible()
  await page
    .getByRole('dialog', { name: 'Edit blacklist' })
    .getByRole('button', { name: 'Cancel' })
    .click()
  await expect(page.getByRole('heading', { name: 'Edit blacklist' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Create blacklist' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create blacklist' })
  const panel = dialog.getByTestId('slide-over-panel')
  const closeButton = dialog.getByRole('button', { name: 'Close panel' })
  await page.waitForTimeout(350)
  await expectInsideViewport(page, panel)
  await expectInsideViewport(page, closeButton)
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
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('blacklist-create-mobile-validation.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
