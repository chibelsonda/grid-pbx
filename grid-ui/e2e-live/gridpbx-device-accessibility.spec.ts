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

async function horizontalOverflow(page: Page): Promise<{
  viewportWidth: number
  documentWidth: number
  offenders: string[]
}> {
  return page.evaluate(() => {
    const viewportWidth = document.documentElement.clientWidth
    const offenders = [...document.querySelectorAll<HTMLElement>('body *')]
      .filter((element) => {
        const bounds = element.getBoundingClientRect()

        return bounds.right > viewportWidth + 1 || bounds.left < -1
      })
      .slice(0, 10)
      .map((element) => {
        const bounds = element.getBoundingClientRect()

        return `${element.tagName.toLowerCase()}.${element.className}[${bounds.left}:${bounds.right}]`
      })

    return {
      viewportWidth,
      documentWidth: document.documentElement.scrollWidth,
      offenders,
    }
  })
}

test.use({ viewport: { width: 390, height: 844 } })

test('keeps Devices inventory and form navigation usable on mobile', async ({ page }, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []

  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/devices(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.goto('/devices')
  await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()

  for (const action of [
    page.getByRole('button', { name: 'Reload projection' }),
    page.getByRole('link', { name: 'Add device' }),
  ]) {
    await expect(action).toBeVisible()
    const bounds = await action.boundingBox()
    expect(bounds).not.toBeNull()
    expect(bounds!.x).toBeGreaterThanOrEqual(0)
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390)
  }

  const table = page.getByRole('table', {
    name: 'Projected devices for the selected Switch account',
  })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(6)

  const inventoryOverflow = await horizontalOverflow(page)
  expect(
    inventoryOverflow.documentWidth,
    `Overflowing elements: ${inventoryOverflow.offenders.join(', ')}`,
  ).toBeLessThanOrEqual(inventoryOverflow.viewportWidth)
  await page.screenshot({
    path: testInfo.outputPath('devices-mobile-inventory.png'),
    fullPage: true,
  })

  const firstDeviceLink = table.locator('tbody tr a').first()
  await expect(firstDeviceLink).toBeVisible()
  await firstDeviceLink.click()
  await expect(page.getByRole('link', { name: 'Edit' })).toBeVisible()
  for (const action of [
    page.getByRole('link', { name: 'Edit' }),
    page.getByRole('button', { name: 'Delete', exact: true }),
  ]) {
    const bounds = await action.boundingBox()
    expect(bounds).not.toBeNull()
    expect(bounds!.x).toBeGreaterThanOrEqual(0)
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390)
  }
  const detailOverflow = await horizontalOverflow(page)
  expect(
    detailOverflow.documentWidth,
    `Overflowing elements: ${detailOverflow.offenders.join(', ')}`,
  ).toBeLessThanOrEqual(detailOverflow.viewportWidth)
  await page.screenshot({
    path: testInfo.outputPath('device-mobile-detail.png'),
    fullPage: true,
  })
  await page.getByRole('link', { name: 'Back to devices' }).click()
  await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()

  await page.getByRole('link', { name: 'Add device' }).click()
  const dialog = page.getByRole('dialog', { name: 'Add device' })
  await expect(dialog).toHaveAttribute('data-headlessui-state', 'open')
  const panel = dialog.getByTestId('slide-over-panel')
  const closeButton = dialog.getByRole('button', { name: 'Close panel' })
  await expect(panel).toBeVisible()
  await expect(closeButton).toBeVisible()
  await page.waitForTimeout(350)
  for (const element of [panel, closeButton]) {
    const bounds = await element.boundingBox()
    expect(bounds).not.toBeNull()
    expect(bounds!.x).toBeGreaterThanOrEqual(0)
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390)
  }

  const formSections = dialog.getByRole('tablist', { name: 'Device form sections' })
  await formSections.getByRole('tab', { name: 'Advanced' }).click()

  const advancedSections = dialog.getByRole('tablist', { name: 'Device advanced sections' })
  await expect(advancedSections).toBeVisible()
  expect(
    await advancedSections.evaluate((element) => element.scrollWidth > element.clientWidth),
  ).toBe(true)
  const options = advancedSections.getByRole('tab', { name: 'Options' })
  await options.scrollIntoViewIfNeeded()
  await options.click()
  await expect(options).toHaveAttribute('aria-selected', 'true')

  const formOverflow = await horizontalOverflow(page)
  expect(
    formOverflow.documentWidth,
    `Overflowing elements: ${formOverflow.offenders.join(', ')}`,
  ).toBeLessThanOrEqual(formOverflow.viewportWidth)
  await page.screenshot({
    path: testInfo.outputPath('device-create-mobile-advanced.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
