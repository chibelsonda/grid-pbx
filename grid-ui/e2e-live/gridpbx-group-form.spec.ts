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

async function addGroupMember(page: Page, type: 'User' | 'Device' | 'Group'): Promise<void> {
  await page.getByRole('button', { name: 'Member type' }).click()
  await page.getByRole('option', { name: type, exact: true }).click()
  await page.getByRole('button', { name: 'Member target' }).click()
  const target = page.getByRole('option').filter({ hasNotText: 'Select target…' }).first()
  await expect(target).toBeVisible()
  await target.click()
  await page.getByRole('button', { name: 'Add', exact: true }).click()
}

test('keeps the Group inventory and form accessible on mobile', async ({ page }, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []

  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:groups|sync\/groups)(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/groups')
  await expect(page.getByRole('heading', { name: 'Groups & Ring Groups' })).toBeVisible()

  for (const action of [
    page.getByRole('button', { name: 'Sync', exact: true }),
    page.getByRole('button', { name: 'New group' }),
    page.getByRole('button', { name: 'Search', exact: true }),
  ]) {
    await expect(action).toBeVisible()
    await expectInsideViewport(page, action)
  }

  const table = page.getByRole('table', { name: 'Groups for the selected Switch account' })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(4)
  await expect(table).toHaveAttribute('aria-busy', 'false')

  await page.getByRole('button', { name: 'New group' }).click()
  await expect(page.getByRole('heading', { name: 'Create group' })).toBeVisible()
  const dialog = page.getByRole('dialog', { name: 'Create group' })
  const panel = dialog.getByTestId('slide-over-panel')
  const closeButton = dialog.getByRole('button', { name: 'Close panel' })
  await page.waitForTimeout(350)
  await expectInsideViewport(page, panel)
  await expectInsideViewport(page, closeButton)
  await expect(dialog.getByRole('group', { name: 'Members' })).toBeVisible()

  await dialog.getByRole('button', { name: 'Music on hold' }).click()
  const options = page.getByRole('listbox')
  await expect(options).toBeVisible()
  const box = await options.boundingBox()
  const viewport = page.viewportSize()
  expect(box).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(box!.x).toBeGreaterThanOrEqual(0)
  expect(box!.y).toBeGreaterThanOrEqual(0)
  expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
  await page.getByRole('option', { name: 'Account default' }).click()

  await dialog.getByRole('button', { name: 'Save group' }).click()
  const name = dialog.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a group name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('group-create-mobile-validation.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('creates, edits, clears, and deletes a disposable Switch Group', async ({ page }) => {
  const issues = collectPageIssues(page)
  const suffix = Date.now().toString().slice(-8)
  const createdName = `E2E Group ${suffix}`
  const updatedName = `${createdName} Updated`

  await page.goto('/groups')
  await expect(page.getByRole('heading', { name: 'Groups & Ring Groups' })).toBeVisible()
  await page.getByRole('button', { name: 'New group' }).click()
  await expect(page.getByRole('heading', { name: 'Create group' })).toBeVisible()
  await page.getByLabel('Name', { exact: true }).fill(createdName)

  await addGroupMember(page, 'User')
  await addGroupMember(page, 'Device')
  await addGroupMember(page, 'Group')
  await expect(page.getByRole('button', { name: 'Remove member 3' })).toBeVisible()

  await page.getByRole('button', { name: 'Music on hold' }).click()
  const musicOptions = page.getByRole('option')
  const musicOptionCount = await musicOptions.count()
  if (musicOptionCount > 1) await musicOptions.nth(1).click()
  else await page.getByRole('option', { name: 'Account default' }).click()

  const createResponsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      /\/api\/v1\/accounts\/[^/]+\/groups$/.test(new URL(response.url()).pathname),
  )
  await page.getByRole('button', { name: 'Save group' }).click()
  expect((await createResponsePromise).status()).toBe(201)
  await expect(page.getByRole('heading', { name: 'Create group' })).toHaveCount(0)

  const createdRow = page.getByRole('row').filter({ hasText: createdName })
  await expect(createdRow).toBeVisible()
  await createdRow.getByRole('button', { name: createdName }).click()
  await expect(page.getByRole('heading', { name: 'Edit group' })).toBeVisible()
  await page.getByLabel('Name', { exact: true }).fill(updatedName)
  for (let member = 0; member < 3; member += 1) {
    await page.getByRole('button', { name: 'Remove member 1' }).click()
  }
  await expect(page.getByText('No members selected.')).toBeVisible()
  await page.getByRole('button', { name: 'Music on hold' }).click()
  await page.getByRole('option', { name: 'Account default' }).click()

  const updateResponsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'PUT' &&
      /\/api\/v1\/accounts\/[^/]+\/groups\/[^/]+$/.test(new URL(response.url()).pathname),
  )
  await page.getByRole('button', { name: 'Save group' }).click()
  expect((await updateResponsePromise).status()).toBe(200)

  const updatedRow = page.getByRole('row').filter({ hasText: updatedName })
  await expect(updatedRow).toBeVisible()
  await expect(updatedRow).toContainText('0')
  await expect(updatedRow).toContainText('Account default')
  await updatedRow.getByRole('button', { name: updatedName }).click()
  await expect(page.getByText('No members selected.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Music on hold' })).toContainText('Account default')

  await page.getByRole('button', { name: 'Delete group' }).click()
  const dialog = page.getByRole('dialog').last()
  await expect(dialog.getByRole('heading', { name: 'Delete group' })).toBeVisible()
  const deleteResponsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'DELETE' &&
      /\/api\/v1\/accounts\/[^/]+\/groups\/[^/]+$/.test(new URL(response.url()).pathname),
  )
  await dialog.getByRole('button', { name: 'Delete group', exact: true }).click()
  expect((await deleteResponsePromise).status()).toBe(204)
  await expect(dialog).toHaveCount(0)
  await expect(page.getByRole('row').filter({ hasText: updatedName })).toHaveCount(0)
  expect(issues).toEqual([])
})
