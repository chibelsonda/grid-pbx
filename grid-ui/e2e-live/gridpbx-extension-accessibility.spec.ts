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

test('keeps People and Extensions inventory and form navigation usable on mobile', async ({
  page,
}, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:extensions|sync\/extensions)(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.goto('/extensions')
  await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()

  const actions = [
    page.getByRole('button', { name: 'Recovery queue' }),
    page.getByRole('button', { name: 'Sync from Switch' }),
    page.getByRole('button', { name: 'Create extension' }),
  ]
  for (const action of actions) {
    await expect(action).toBeVisible()
    const bounds = await action.boundingBox()
    expect(bounds).not.toBeNull()
    expect(bounds!.x).toBeGreaterThanOrEqual(0)
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390)
  }

  const table = page.getByRole('table', {
    name: 'Projected people and extensions for the selected Switch account',
  })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(6)
  const inventoryOverflow = await horizontalOverflow(page)
  expect(
    inventoryOverflow.documentWidth,
    `Overflowing elements: ${inventoryOverflow.offenders.join(', ')}`,
  ).toBeLessThanOrEqual(inventoryOverflow.viewportWidth)
  await page.screenshot({
    path: testInfo.outputPath('extensions-mobile-inventory.png'),
    fullPage: true,
  })

  await page.getByRole('button', { name: 'Create extension' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create extension' })
  await expect(dialog).toHaveAttribute('data-headlessui-state', 'open')
  await expect(dialog.getByTestId('slide-over-panel')).toBeVisible()
  const formSections = dialog.getByRole('tablist', { name: 'Extension form sections' })
  await formSections.getByRole('tab', { name: 'Advanced' }).click()

  const advancedSections = dialog.getByRole('tablist', { name: 'Extension advanced sections' })
  await expect(advancedSections).toBeVisible()
  expect(
    await advancedSections.evaluate((element) => element.scrollWidth > element.clientWidth),
  ).toBe(true)
  const metaflows = advancedSections.getByRole('tab', { name: 'Metaflows' })
  await metaflows.scrollIntoViewIfNeeded()
  await metaflows.click()
  await expect(metaflows).toHaveAttribute('aria-selected', 'true')
  const formOverflow = await horizontalOverflow(page)
  expect(
    formOverflow.documentWidth,
    `Overflowing elements: ${formOverflow.offenders.join(', ')}`,
  ).toBeLessThanOrEqual(formOverflow.viewportWidth)
  await page.screenshot({
    path: testInfo.outputPath('extension-create-mobile-advanced.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
