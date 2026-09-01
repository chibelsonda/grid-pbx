import { expect, test, type Page } from '@playwright/test'

type DisposableExtension = {
  deleteUrl: string
  number: string
}

type DirectoryMutationBody = {
  data: {
    id: string
    name: string
    confirm_match: boolean
    min_dtmf: number
    max_dtmf: number
    sort_by: 'first_name' | 'last_name'
    members: Array<{ extension: { id: string; label: string } | null; resolved: boolean }>
  }
}

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

async function deleteDisposableExtension(
  page: Page,
  extension: DisposableExtension,
): Promise<void> {
  const cleanup = await page.evaluate(async ({ deleteUrl, number }) => {
    const token = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const response = await fetch(deleteUrl, {
      method: 'DELETE',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify({ confirmation: number }),
    })

    return { status: response.status, body: await response.text() }
  }, extension)

  if (cleanup.status !== 204) {
    throw new Error(`Disposable Extension cleanup failed: ${cleanup.body}`)
  }
}

async function deleteDirectoryByUrl(page: Page, deleteUrl: string): Promise<void> {
  const cleanup = await page.evaluate(async (url) => {
    const token = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const response = await fetch(url, {
      method: 'DELETE',
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
    })

    return { status: response.status, body: await response.text() }
  }, deleteUrl)

  if (cleanup.status !== 204 && cleanup.status !== 404) {
    throw new Error(`Disposable Directory cleanup failed: ${cleanup.body}`)
  }
}

test('keeps the Directory inventory and form accessible on mobile', async ({ page }, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []

  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:directories|sync\/directories)(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/directories')
  await expect(page.getByRole('heading', { name: 'Directories' })).toBeVisible()

  for (const action of [
    page.getByRole('button', { name: 'Sync', exact: true }),
    page.getByRole('button', { name: 'New directory' }),
    page.getByRole('button', { name: 'Search', exact: true }),
  ]) {
    await expect(action).toBeVisible()
    await expectInsideViewport(page, action)
  }

  const table = page.getByRole('table', { name: 'Directories for the selected Switch account' })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(5)
  await expect(table).toHaveAttribute('aria-busy', 'false')

  await page.getByRole('button', { name: 'New directory' }).click()
  await expect(page.getByRole('heading', { name: 'Create directory' })).toBeVisible()
  const dialog = page.getByRole('dialog', { name: 'Create directory' })
  const panel = dialog.getByTestId('slide-over-panel')
  const closeButton = dialog.getByRole('button', { name: 'Close panel' })
  await page.waitForTimeout(350)
  await expectInsideViewport(page, panel)
  await expectInsideViewport(page, closeButton)
  await expect(dialog.getByRole('group', { name: 'Directory members' })).toBeVisible()

  await dialog.getByRole('tab', { name: 'Advanced' }).click()
  await dialog.getByRole('button', { name: 'Sort names by' }).click()
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
  await page.getByRole('option', { name: 'Last name' }).click()

  await dialog.getByRole('button', { name: 'Save directory' }).click()
  const name = dialog.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a directory name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  const basicTab = dialog.getByRole('tab', { name: 'Basic' })
  const advancedTab = dialog.getByRole('tab', { name: 'Advanced' })
  await expect(basicTab).toHaveAttribute('aria-selected', 'true')
  await expect(basicTab).toHaveClass(/border-brand-500/)
  await expect(advancedTab).toHaveClass(/border-transparent/)
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('directory-create-mobile-validation.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('creates, edits, clears, and deletes a Directory with a public Extension member', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const suffix = Date.now().toString()
  const number = suffix.slice(-8)
  const memberName = 'GridPBX Directory Member'
  const directoryName = `E2E Directory ${suffix}`
  const editedName = `${directoryName} Edited`
  let extension: DisposableExtension | null = null
  let directoryDeleteUrl: string | null = null

  try {
    await page.goto('/extensions')
    await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
    await page.getByRole('button', { name: 'Create extension' }).click()
    await page.getByLabel('First name').fill('GridPBX')
    await page.getByLabel('Last name').fill('Directory Member')
    await page.getByLabel('Extension number').fill(number)
    const voicemail = page.locator('article').filter({ hasText: 'Voicemail fallback' })
    await voicemail.getByRole('switch', { name: 'Create' }).click()

    const createExtension = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Create extension', exact: true }).last().click()
    const extensionResponse = await createExtension
    if (extensionResponse.status() !== 201) {
      throw new Error(
        `Disposable Directory member creation failed: ${await extensionResponse.text()}`,
      )
    }
    const extensionResult = (await extensionResponse.json()) as { data: { id: string } }
    const extensionUrl = new URL(extensionResponse.url())
    extension = {
      deleteUrl: `${extensionUrl.origin}${extensionUrl.pathname}/${extensionResult.data.id}`,
      number,
    }

    await page.goto('/directories')
    await expect(page.getByRole('heading', { name: 'Directories' })).toBeVisible()
    await page.getByRole('button', { name: 'New directory' }).click()
    await page.getByLabel('Name', { exact: true }).fill(directoryName)
    const member = page.getByRole('checkbox', { name: new RegExp(memberName) })
    await expect(member).toBeVisible()
    await member.check()

    await page.getByRole('tab', { name: 'Advanced' }).click()
    await page.getByRole('button', { name: 'Sort names by' }).click()
    await page.getByRole('option', { name: 'First name' }).click()
    await page.getByRole('switch', { name: 'Confirm a single match' }).click()
    await page.getByLabel('Minimum digits').fill('2')
    await page.getByLabel('Maximum digits').fill('8')

    const createDirectory = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/directories$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save directory' }).click()
    const createResponse = await createDirectory
    expect(createResponse.status()).toBe(201)
    const created = (await createResponse.json()) as DirectoryMutationBody
    directoryDeleteUrl = `${new URL(createResponse.url()).origin}${new URL(createResponse.url()).pathname}/${created.data.id}`
    expect(created.data).toMatchObject({
      name: directoryName,
      confirm_match: false,
      min_dtmf: 2,
      max_dtmf: 8,
      sort_by: 'first_name',
    })
    expect(created.data.members).toEqual([
      expect.objectContaining({
        extension: expect.objectContaining({ id: extensionResult.data.id, label: memberName }),
        resolved: true,
      }),
    ])

    await expect(page.getByText(directoryName, { exact: true })).toBeVisible()
    await page.getByText(directoryName, { exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Edit directory' })).toBeVisible()
    await expect(page.getByRole('checkbox', { name: new RegExp(memberName) })).toBeChecked()
    await page.getByLabel('Name', { exact: true }).fill(editedName)
    await page.getByRole('tab', { name: 'Advanced' }).click()
    await page.getByRole('button', { name: 'Sort names by' }).click()
    await page.getByRole('option', { name: 'Last name' }).click()
    await page.getByRole('switch', { name: 'Confirm a single match' }).click()
    await page.getByLabel('Minimum digits').fill('4')
    await page.getByLabel('Maximum digits').fill('10')

    const updateDirectory = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url() === directoryDeleteUrl,
    )
    await page.getByRole('button', { name: 'Save directory' }).click()
    const updateResponse = await updateDirectory
    expect(updateResponse.status()).toBe(200)
    const updated = (await updateResponse.json()) as DirectoryMutationBody
    expect(updated.data).toMatchObject({
      name: editedName,
      confirm_match: true,
      min_dtmf: 4,
      max_dtmf: 10,
      sort_by: 'last_name',
    })
    expect(updated.data.members).toHaveLength(1)

    await page.getByText(editedName, { exact: true }).click()
    const clearMember = page.getByRole('checkbox', { name: new RegExp(memberName) })
    await expect(clearMember).toBeChecked()
    await clearMember.uncheck()
    await page.getByRole('tab', { name: 'Advanced' }).click()
    await page.getByLabel('Maximum digits').fill('0')

    const clearDirectory = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url() === directoryDeleteUrl,
    )
    await page.getByRole('button', { name: 'Save directory' }).click()
    const clearResponse = await clearDirectory
    expect(clearResponse.status()).toBe(200)
    const cleared = (await clearResponse.json()) as DirectoryMutationBody
    expect(cleared.data.max_dtmf).toBe(0)
    expect(cleared.data.members).toEqual([])

    await page.getByText(editedName, { exact: true }).click()
    const deletePath = new URL(directoryDeleteUrl).pathname
    const deleteDirectory = page.waitForResponse(
      (response) =>
        response.request().method() === 'DELETE' && new URL(response.url()).pathname === deletePath,
    )
    await page.getByRole('button', { name: 'Delete directory' }).click()
    const confirmDelete = page
      .getByRole('dialog', { name: 'Delete directory' })
      .getByRole('button', { name: 'Delete directory', exact: true })
    await expect(confirmDelete).toBeVisible()
    await confirmDelete.click()
    expect((await deleteDirectory).status()).toBe(204)
    directoryDeleteUrl = null
    await expect(page.getByText(editedName, { exact: true })).toHaveCount(0)
    expect(issues).toEqual([])
  } finally {
    if (directoryDeleteUrl) {
      await deleteDirectoryByUrl(page, directoryDeleteUrl).catch(() => undefined)
    }
    if (extension) {
      await deleteDisposableExtension(page, extension)
    }
  }
})
