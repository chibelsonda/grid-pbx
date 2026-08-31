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

test('keeps Menu validation inline and its media listbox inside the viewport', async ({ page }) => {
  const issues = collectPageIssues(page)
  await page.goto('/menus')
  await expect(page.getByRole('heading', { name: 'Menus & IVR' })).toBeVisible()
  await page.getByRole('button', { name: 'New menu' }).click()
  await expect(page.getByRole('heading', { name: 'Create menu' })).toBeVisible()

  await page.getByRole('button', { name: 'Greeting media' }).click()
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
  await page.getByRole('option', { name: 'Switch system prompt' }).click()

  const formSections = page.getByRole('tablist', { name: 'Form sections' })
  await expect(formSections.getByRole('tab')).toHaveText(['Basic', 'Advanced'])
  await formSections.getByRole('tab', { name: 'Advanced' }).click()
  const advancedSections = page.getByRole('tablist', { name: 'Menu advanced sections' })
  await expect(advancedSections.getByRole('tab')).toHaveText([
    'Basic',
    'Extension Dialing',
    'Options',
  ])
  await advancedSections.getByRole('tab', { name: 'Options' }).click()
  await page.getByRole('switch', { name: 'Suppress result prompts' }).click()
  await expect(
    page.getByText('Disable invalid, transfer, and exit prompts at runtime.'),
  ).toBeVisible()
  const promptToggles = page.getByRole('switch', { name: 'Enabled' })
  await expect(promptToggles).toHaveCount(3)
  for (let index = 0; index < 3; index += 1) {
    await expect(promptToggles.nth(index)).toBeDisabled()
  }

  const timeout = page.getByLabel('Initial digit timeout (ms)')
  await timeout.fill('60001')
  await page.getByRole('button', { name: 'Save menu' }).click()

  const name = page.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a menu name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  await formSections.getByRole('tab', { name: 'Advanced' }).click()
  await advancedSections.getByRole('tab', { name: 'Options' }).click()
  await expect(page.getByLabel('Initial digit timeout (ms)')).toHaveAttribute(
    'aria-invalid',
    'true',
  )
  await expect(page.getByLabel('Initial digit timeout (ms)')).toHaveClass(/border-red-400/)
  expect(issues).toEqual([])
})

test('round-trips Menu runtime prompt suppression and write-only PIN clearing', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const suffix = Date.now().toString().slice(-8)
  const menuName = `E2E Menu ${suffix}`
  let cleanupUrl: string | null = null

  try {
    const initialResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        /\/api\/v1\/accounts\/[^/]+\/menus$/.test(new URL(response.url()).pathname),
    )
    await page.goto('/menus')
    const initialResponse = await initialResponsePromise
    const initialPayload = (await initialResponse.json()) as {
      data: Array<{ id: string; name: string }>
    }
    const collectionUrl = initialResponse.url().split('?')[0]!

    for (const stale of initialPayload.data.filter((menu) => menu.name.startsWith('E2E Menu '))) {
      expect(await deleteApiResource(page, `${collectionUrl}/${stale.id}`)).toBe(204)
    }
    if (initialPayload.data.some((menu) => menu.name.startsWith('E2E Menu '))) await page.reload()

    await page.getByRole('button', { name: 'New menu' }).click()
    let dialog = page.getByRole('dialog', { name: 'Create menu' })
    await dialog.getByLabel('Name', { exact: true }).fill(menuName)
    await dialog.getByRole('textbox', { name: 'Recording PIN', exact: true }).fill('4826')

    const createResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'POST' && /\/menus$/.test(response.url()),
    )
    await dialog.getByRole('button', { name: 'Save menu' }).click()
    const createResponse = await createResponsePromise
    expect(createResponse.status()).toBe(201)
    const created = (await createResponse.json()) as {
      data: { id: string; record_pin_configured: boolean }
    }
    expect(created.data.id).toMatch(/^[0-9a-f-]{36}$/)
    expect(created.data.record_pin_configured).toBe(true)
    expect(JSON.stringify(created)).not.toContain('4826')
    expect(JSON.stringify(created)).not.toContain('switch_resource_id')
    cleanupUrl = `${createResponse.url()}/${created.data.id}`

    let row = page.getByRole('row', { name: new RegExp(menuName) })
    await row.click()
    dialog = page.getByRole('dialog', { name: 'Edit menu' })
    await dialog.getByRole('textbox', { name: 'Recording PIN', exact: true }).fill('5937')
    await dialog
      .getByRole('tablist', { name: 'Form sections' })
      .getByRole('tab', { name: 'Advanced' })
      .click()
    await dialog
      .getByRole('tablist', { name: 'Menu advanced sections' })
      .getByRole('tab', { name: 'Options' })
      .click()
    await dialog.getByRole('switch', { name: 'Suppress result prompts' }).click()

    const updateResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url() === cleanupUrl,
    )
    await dialog.getByRole('button', { name: 'Save menu' }).click()
    const updateResponse = await updateResponsePromise
    expect(updateResponse.status(), await updateResponse.text()).toBe(200)

    row = page.getByRole('row', { name: new RegExp(menuName) })
    await row.click()
    dialog = page.getByRole('dialog', { name: 'Edit menu' })
    await dialog
      .getByRole('tablist', { name: 'Form sections' })
      .getByRole('tab', { name: 'Advanced' })
      .click()
    await dialog
      .getByRole('tablist', { name: 'Menu advanced sections' })
      .getByRole('tab', { name: 'Options' })
      .click()
    await expect(dialog.getByRole('switch', { name: 'Suppress result prompts' })).toBeChecked()
    await expect(dialog.getByRole('switch', { name: 'Enabled' })).toHaveCount(3)
    for (const toggle of await dialog.getByRole('switch', { name: 'Enabled' }).all()) {
      await expect(toggle).not.toBeChecked()
      await expect(toggle).toBeDisabled()
    }
    await dialog
      .getByRole('tablist', { name: 'Form sections' })
      .getByRole('tab', { name: 'Basic' })
      .click()
    await dialog.getByRole('switch', { name: 'Remove current recording PIN' }).click()

    const clearResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'PUT' && response.url() === cleanupUrl,
    )
    await dialog.getByRole('button', { name: 'Save menu' }).click()
    const clearResponse = await clearResponsePromise
    expect(clearResponse.status(), await clearResponse.text()).toBe(200)
    const cleared = (await clearResponse.json()) as {
      data: { record_pin_configured: boolean; invalid_media_enabled: boolean }
    }
    expect(cleared.data.record_pin_configured).toBe(false)
    expect(cleared.data.invalid_media_enabled).toBe(false)

    row = page.getByRole('row', { name: new RegExp(menuName) })
    await row.click()
    dialog = page.getByRole('dialog', { name: 'Edit menu' })
    await expect(dialog.getByRole('switch', { name: 'Remove current recording PIN' })).toHaveCount(
      0,
    )
    await expect(
      dialog.getByRole('textbox', { name: 'Recording PIN', exact: true }),
    ).toHaveAttribute('placeholder', 'Optional')
    await dialog.getByRole('button', { name: 'Delete menu' }).click()

    const deleteResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'DELETE' && response.url() === cleanupUrl,
    )
    const confirmationDescription = page.getByText(
      'Delete this menu after checking its call-routing dependencies?',
      { exact: true },
    )
    const confirmation = confirmationDescription.locator('xpath=ancestor::*[@role="dialog"][1]')
    const confirmDelete = confirmation.getByRole('button', { name: 'Delete menu', exact: true })
    await expect(confirmDelete).toBeVisible()
    await confirmDelete.click()
    expect((await deleteResponsePromise).status()).toBe(204)
    cleanupUrl = null
    await expect(page.getByRole('row', { name: new RegExp(menuName) })).toHaveCount(0)

    const syncResponsePromise = page.waitForResponse(
      (response) => response.request().method() === 'POST' && /\/sync\/menus$/.test(response.url()),
    )
    const syncButton = page.getByRole('button', { name: 'Sync' })
    await syncButton.click()
    expect((await syncResponsePromise).status()).toBe(202)
    await expect(syncButton).toBeEnabled({ timeout: 30_000 })
    await expect(page.getByRole('row', { name: new RegExp(menuName) })).toHaveCount(0)
    expect(issues).toEqual([])
  } finally {
    if (cleanupUrl) await deleteApiResource(page, cleanupUrl)
  }
})
