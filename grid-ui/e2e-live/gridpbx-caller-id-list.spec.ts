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

async function deleteApiResource(page: Page, url: string): Promise<number> {
  const xsrfCookie = (await page.context().cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN')
  const response = await page.request.delete(url, {
    headers: {
      Accept: 'application/json',
      Origin: new URL(page.url()).origin,
      Referer: page.url(),
      ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.value) } : {}),
    },
    failOnStatusCode: false,
  })

  return response.status()
}

test('manages Caller-ID List entries in a right-side panel with inline validation', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.route('**/api/v1/accounts/*/caller-id-lists?*', async (route) => {
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

  await page.goto('/caller-id-lists')
  await expect(page.getByRole('heading', { name: 'Caller-ID Lists', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'New list' }).click()

  const dialog = page.getByRole('dialog', { name: 'Create Caller-ID List' })
  const panel = page.getByTestId('slide-over-panel')
  await expect(page.getByRole('heading', { name: 'Create Caller-ID List' })).toBeVisible()
  await expect(panel).toBeVisible()
  await expect(dialog.getByRole('tab', { name: 'Basic' })).toHaveAttribute('aria-selected', 'true')
  await expect(dialog.getByLabel('Name', { exact: true })).toBeVisible()
  await expect(dialog.getByLabel('Description')).toBeHidden()
  const viewport = page.viewportSize()
  expect(viewport).not.toBeNull()
  await expect
    .poll(async () => {
      const box = await panel.boundingBox()
      return Boolean(box && box.x > 0 && box.x + box.width <= viewport!.width)
    })
    .toBe(true)

  await dialog.getByRole('button', { name: 'Pattern' }).click()
  await dialog.getByLabel('Regular expression').fill('(?R)')
  await dialog.getByRole('button', { name: 'Save Caller-ID List' }).click()

  const name = dialog.getByLabel('Name', { exact: true })
  const pattern = dialog.getByLabel('Regular expression')
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(pattern).toHaveAttribute('aria-invalid', 'true')
  await expect(pattern).toHaveClass(/border-red-400/)
  await expect(dialog.getByText('Enter a Caller-ID List name.')).toBeVisible()
  await expect(dialog.getByText('Enter a supported regular expression.')).toBeVisible()
  await expect(dialog.getByRole('tab', { name: 'Basic' })).toHaveAttribute('aria-selected', 'true')

  await dialog.getByRole('tab', { name: 'Advanced' }).click()
  await expect(dialog.getByLabel('Description')).toBeVisible()
  await expect(dialog.getByLabel('Organization')).toBeVisible()
  await expect(dialog.getByLabel('Name', { exact: true })).toBeHidden()
  await dialog.getByRole('tab', { name: 'Basic' }).click()

  await dialog.getByRole('button', { name: 'Match type' }).click()
  const listbox = page.getByRole('listbox')
  await expect(listbox).toBeVisible()
  const listboxBox = await listbox.boundingBox()
  expect(listboxBox).not.toBeNull()
  expect(listboxBox!.x + listboxBox!.width).toBeLessThanOrEqual(viewport!.width)
  expect(listboxBox!.y + listboxBox!.height).toBeLessThanOrEqual(viewport!.height)
  expect(issues).toEqual([])
})

test('round-trips a Caller-ID List through the live GridPBX and Switch APIs', async ({ page }) => {
  const issues = collectPageIssues(page)
  const suffix = Date.now().toString().slice(-8)
  const listName = `E2E CID list ${suffix}`
  let cleanupUrl: string | null = null

  try {
    const initialResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        /\/api\/v1\/accounts\/[^/]+\/caller-id-lists$/.test(new URL(response.url()).pathname),
    )
    await page.goto('/caller-id-lists')
    const initialResponse = await initialResponsePromise
    const initialPayload = (await initialResponse.json()) as {
      data: Array<{ id: string; name: string }>
    }
    const listCollectionUrl = initialResponse.url().split('?')[0]!
    const staleIds = initialPayload.data
      .filter((record) => record.name.startsWith('E2E CID list '))
      .map((record) => record.id)
    for (const id of staleIds) {
      expect(await deleteApiResource(page, `${listCollectionUrl}/${id}`)).toBe(204)
    }
    if (staleIds.length) await page.reload()

    await expect(page.getByRole('heading', { name: 'Caller-ID Lists', exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'New list' }).click()

    let dialog = page.getByRole('dialog', { name: 'Create Caller-ID List' })
    await dialog.getByLabel('Name', { exact: true }).fill(listName)
    await dialog.getByRole('tab', { name: 'Advanced' }).click()
    await dialog.getByLabel('Description').fill('Isolated lifecycle verification')
    await dialog.getByRole('tab', { name: 'Basic' }).click()
    await dialog.getByRole('button', { name: 'Number' }).click()
    await dialog.getByLabel('Number or prefix').fill(`+1555${suffix}`)
    await dialog.getByLabel('Display name').fill('Initial prefix')

    const createResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' && /\/caller-id-lists$/.test(response.url()),
    )
    await dialog.getByRole('button', { name: 'Save Caller-ID List' }).click()
    const createResponse = await createResponsePromise
    expect(createResponse.status()).toBe(201)
    const created = (await createResponse.json()) as { data: { id: string } }
    cleanupUrl = `${createResponse.url()}/${created.data.id}`

    let row = page.getByRole('row', { name: new RegExp(listName) })
    await expect(row).toBeVisible()
    await row.click()
    dialog = page.getByRole('dialog', { name: 'Edit Caller-ID List' })
    await dialog.getByRole('button', { name: 'Match type' }).click()
    await page.getByRole('option', { name: 'Regular expression' }).click()
    await dialog.getByLabel('Regular expression').fill('^\\+632[0-9]+$')
    await dialog.getByLabel('Display name').fill('Manila callers')

    const updateResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url() === cleanupUrl,
    )
    await dialog.getByRole('button', { name: 'Save Caller-ID List' }).click()
    const updateResponse = await updateResponsePromise
    const updateBody = await updateResponse.text()
    expect(updateResponse.status(), updateBody).toBe(200)

    row = page.getByRole('row', { name: new RegExp(listName) })
    await row.click()
    dialog = page.getByRole('dialog', { name: 'Edit Caller-ID List' })
    await expect(dialog.getByLabel('Regular expression')).toHaveValue('^\\+632[0-9]+$')
    await dialog.getByRole('button', { name: 'Remove entry 1' }).click()
    const clearResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url() === cleanupUrl,
    )
    await dialog.getByRole('button', { name: 'Save Caller-ID List' }).click()
    const clearResponse = await clearResponsePromise
    expect(clearResponse.status(), await clearResponse.text()).toBe(200)

    row = page.getByRole('row', { name: new RegExp(listName) })
    await expect(row.getByText('0', { exact: true })).toBeVisible()
    await row.click()
    dialog = page.getByRole('dialog', { name: 'Edit Caller-ID List' })
    await expect(
      dialog.getByText('No match entries yet. An empty list never matches a caller.'),
    ).toBeVisible()
    await dialog.getByRole('button', { name: 'Delete Caller-ID List' }).click()

    const deleteResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'DELETE' && response.url() === cleanupUrl,
    )
    await page.getByRole('button', { name: 'Delete list' }).click()
    expect((await deleteResponsePromise).status()).toBe(204)
    cleanupUrl = null
    await expect(page.getByRole('row', { name: new RegExp(listName) })).toHaveCount(0)
    expect(issues).toEqual([])
  } finally {
    if (cleanupUrl) {
      await deleteApiResource(page, cleanupUrl)
    }
  }
})
