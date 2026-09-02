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

async function expectNoDocumentOverflow(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }))

  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport)
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

test.use({ viewport: { width: 390, height: 844 } })

test('keeps Call Routing inventory, workspace, palette, and node dialog usable on mobile', async ({
  page,
}, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []

  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/callflows(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.goto('/call-routing')
  await expect(page.getByRole('heading', { name: 'Callflows', level: 1 })).toBeVisible()
  await expect(page.getByText('Loading projected callflows…')).toHaveCount(0)

  for (const action of [
    page.getByRole('button', { name: 'Create callflow' }),
    page.getByRole('button', { name: 'Synchronize routing' }),
  ]) {
    await expect(action).toBeVisible()
    await expectInsideViewport(page, action)
  }
  const applyFilters = page.getByRole('button', { name: 'Apply filters' })
  await expect(applyFilters).toBeVisible()

  const table = page.getByRole('table', {
    name: 'Projected callflows for the selected Switch account',
  })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(6)
  await expectNoDocumentOverflow(page)
  await page.screenshot({
    path: testInfo.outputPath('callflows-mobile-inventory.png'),
    fullPage: true,
  })

  const firstRoute = table.locator('tbody tr').first().getByRole('button').first()
  await expect(firstRoute).toBeVisible()
  await firstRoute.click()
  await expect(page.getByRole('region', { name: 'Callflow workspace' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Back to callflows' })).toBeVisible()
  await expectInsideViewport(page, page.getByRole('button', { name: 'Back to callflows' }))
  await expectInsideViewport(page, page.getByRole('button', { name: 'Refresh callflow nodes' }))
  await expectNoDocumentOverflow(page)
  await page.screenshot({
    path: testInfo.outputPath('callflow-mobile-workspace.png'),
    fullPage: true,
  })

  await page.getByRole('button', { name: 'Back to callflows' }).click()
  await expect(page.getByRole('heading', { name: 'Callflows', level: 1 })).toBeVisible()
  await page.getByRole('button', { name: 'Create callflow' }).click()
  await expect(page.getByRole('region', { name: 'Create callflow canvas' })).toBeVisible()

  const catalog = page.getByRole('region', { name: 'Callflow action catalog' })
  await catalog.scrollIntoViewIfNeeded()
  await expect(catalog).toBeVisible()
  const rootAction = catalog.getByRole('button', { name: /^Use .+ as root action$/ }).first()
  await expect(rootAction).toBeEnabled()
  await rootAction.click()

  const dialog = page.getByRole('dialog')
  await expect(dialog).toHaveAttribute('data-headlessui-state', 'open')
  const closeDialog = dialog.getByRole('button', { name: 'Close node information' })
  await expect(closeDialog).toBeVisible()
  await expectInsideViewport(page, closeDialog)
  await expectNoDocumentOverflow(page)
  await closeDialog.click()
  await expect(dialog).toHaveCount(0)

  await expect(page.getByRole('button', { name: 'Close create callflow' })).toBeVisible()
  await expectInsideViewport(page, page.getByRole('button', { name: 'Close create callflow' }))
  await page.screenshot({
    path: testInfo.outputPath('callflow-mobile-create.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
