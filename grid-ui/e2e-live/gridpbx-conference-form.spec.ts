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

test('shows schema-backed Conference sounds with inline validation and bounded listboxes', async ({ page }) => {
  const issues = collectPageIssues(page)
  await page.goto('/conferences')
  await expect(page.getByRole('heading', { name: 'Conferences' })).toBeVisible()
  await page.getByRole('button', { name: 'New conference' }).click()
  await expect(page.getByRole('heading', { name: 'Create conference' })).toBeVisible()

  await page.getByRole('button', { name: 'Participant entry tone' }).click()
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
  await page.getByRole('option', { name: 'Play selected media' }).click()
  await expect(page.getByRole('button', { name: 'Entry tone media' })).toBeVisible()

  await page.getByLabel('Member numbers').fill('not-a-number')
  await page.getByRole('button', { name: 'Save conference' }).click()
  const name = page.getByLabel('Name', { exact: true })
  const memberNumbers = page.getByLabel('Member numbers')
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(memberNumbers).toHaveAttribute('aria-invalid', 'true')
  await expect(memberNumbers).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a conference name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('creates, edits, and removes Conference tone configuration', async ({ page }) => {
  const issues = collectPageIssues(page)
  const suffix = String(Date.now()).slice(-7)
  const name = `E2E conference ${suffix}`
  let createdId: string | null = null

  try {
    await page.goto('/conferences')
    await page.getByRole('button', { name: 'New conference' }).click()
    await page.getByLabel('Name', { exact: true }).fill(name)
    await page.getByLabel('General conference numbers').fill(`8${suffix}`)
    await page.getByLabel('Member numbers').fill(`7${suffix}`)
    await page.getByRole('button', { name: 'Participant entry tone' }).click()
    await page.getByRole('option', { name: 'Do not play a tone' }).click()

    const creation = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/conferences$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save conference' }).click()
    const createResponse = await creation
    expect(createResponse.status()).toBe(201)
    const created = (await createResponse.json()) as {
      data: { id: string; entry_tone: { mode: string }; exit_tone: { mode: string } }
    }
    createdId = created.data.id
    expect(created.data.entry_tone.mode).toBe('disabled')
    expect(created.data.exit_tone.mode).toBe('enabled')

    await expect(page.getByRole('heading', { name: 'Create conference' })).toHaveCount(0)
    await page.getByText(name, { exact: true }).click()
    await page.getByRole('button', { name: 'Participant entry tone' }).click()
    await page.getByRole('option', { name: 'Play the standard tone' }).click()
    const update = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/conferences/${createdId}`),
    )
    await page.getByRole('button', { name: 'Save conference' }).click()
    const updateResponse = await update
    expect(updateResponse.status()).toBe(200)
    expect(((await updateResponse.json()) as { data: { entry_tone: { mode: string } } }).data.entry_tone.mode).toBe('enabled')
  } finally {
    if (createdId !== null) {
      await page.goto('/conferences')
      const row = page.getByText(name, { exact: true })

      if (await row.isVisible()) {
        await row.click()
        const deletion = page.waitForResponse(
          (response) =>
            response.request().method() === 'DELETE' &&
            new URL(response.url()).pathname.endsWith(`/conferences/${createdId}`),
        )
        await page.getByRole('button', { name: 'Delete conference' }).click()
        await page.getByRole('dialog').getByRole('button', { name: 'Delete conference' }).click()
        expect((await deletion).status()).toBe(204)
      }
    }
  }

  expect(issues).toEqual([])
})
