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

  const firstExtensionRow = table.locator('tbody tr').first()
  await expect(firstExtensionRow).toBeVisible()
  await firstExtensionRow.getByRole('cell').nth(2).click()
  await expect(page).toHaveURL(/\/extensions\/[0-9a-f-]+$/)
  await page.goBack()
  await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()

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

test.describe('desktop extension form', () => {
  test.use({ viewport: { width: 1440, height: 1000 } })

  test('fits every Advanced tab without a horizontal scrollbar', async ({ page }) => {
    const issues = collectPageIssues(page)

    await page.goto('/extensions')
    await page.getByRole('button', { name: 'Create extension' }).click()

    const dialog = page.getByRole('dialog', { name: 'Create extension' })
    const panel = dialog.getByTestId('slide-over-panel')
    const actions = panel.locator('.slide-over-actions:visible')
    await expect(panel).toHaveAttribute('data-width', 'extra-wide')
    const [panelBox, actionsBox] = await Promise.all([panel.boundingBox(), actions.boundingBox()])
    expect(panelBox).not.toBeNull()
    expect(actionsBox).not.toBeNull()
    expect(actionsBox!.x).toBeCloseTo(panelBox!.x, 0)
    expect(actionsBox!.width).toBeCloseTo(panelBox!.width, 0)
    const formSections = dialog.getByRole('tablist', { name: 'Extension form sections' })
    const basicTab = formSections.getByRole('tab', { name: 'Basic', exact: true })
    const advancedTab = formSections.getByRole('tab', { name: 'Advanced', exact: true })
    await expect(formSections).toHaveClass(/bg-slate-100/)
    await expect(formSections).toHaveClass(/rounded-lg/)
    await expect(formSections).toHaveCSS('padding', '2px')
    await expect(formSections.locator('svg')).toHaveCount(0)
    const formSectionBox = await formSections.boundingBox()
    expect(formSectionBox).not.toBeNull()
    expect(formSectionBox!.width).toBeLessThan(panelBox!.width / 2)
    expect(formSectionBox!.height).toBeLessThanOrEqual(38)
    await expect(basicTab).toHaveClass(/bg-white/)
    await expect(basicTab).toHaveClass(/text-brand-600/)

    await advancedTab.click()
    await expect(advancedTab).toHaveClass(/bg-white/)
    await expect(basicTab).toHaveClass(/bg-transparent/)

    const advancedSections = dialog.getByRole('tablist', {
      name: 'Extension advanced sections',
    })
    await expect(advancedSections).toHaveClass(/border-b/)
    await expect(advancedSections).toHaveClass(/bg-slate-50\/70/)
    const advancedSurface = advancedSections.locator('xpath=ancestor::article[1]')
    await expect(advancedSurface).toHaveClass(/card-surface/)
    const advancedTabLabels = (await advancedSections.getByRole('tab').allTextContents()).map(
      (label) => label.trim(),
    )
    expect(advancedTabLabels).toEqual([
      'Caller ID',
      'Options',
      'Call Forward',
      'Password Management',
      'Hot Desking',
      'Restrictions',
      'Recording',
      'Media',
      'Routing & Profile',
      'Metaflows',
    ])

    await advancedSections.getByRole('tab', { name: 'Caller ID', exact: true }).click()
    await expect(dialog.getByRole('heading', { name: 'Presence identity' })).toBeVisible()
    await expect(
      dialog.getByTestId('extension-advanced-caller-id').getByText('Presence ID', { exact: true }),
    ).toBeVisible()

    await advancedSections.getByRole('tab', { name: 'Options', exact: true }).click()
    await expect(
      advancedSurface.getByRole('heading', { name: 'User calling options' }),
    ).toBeVisible()
    await expect(advancedSurface.getByRole('heading', { name: 'Music on hold' })).toBeVisible()
    await expect(
      dialog.getByTestId('extension-advanced-options').getByText('Presence ID', { exact: true }),
    ).toHaveCount(0)

    await advancedSections.getByRole('tab', { name: 'Media', exact: true }).click()
    await expect(dialog.getByRole('heading', { name: 'Media and endpoint audio' })).toBeVisible()
    await expect(
      dialog.getByTestId('extension-advanced-media').getByText('Music on hold', { exact: true }),
    ).toHaveCount(0)
    const tabWidths = await advancedSections.evaluate((element) => ({
      client: element.clientWidth,
      scroll: element.scrollWidth,
    }))

    expect(tabWidths.scroll).toBeLessThanOrEqual(tabWidths.client + 1)
    expect(issues).toEqual([])
  })
})
