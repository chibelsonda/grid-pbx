import { expect, test, type Page } from '@playwright/test'
import { expectControlRowAligned } from './support/formAlignment.js'

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

test('separates available Queue configuration from unavailable live ACDc controls', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:queues|agents)(?:\/|$)/.test(request.url())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })
  const optionsResponse = page.waitForResponse((response) =>
    /\/api\/v1\/accounts\/[^/]+\/queues\/options$/.test(new URL(response.url()).pathname),
  )

  await page.goto('/queues')
  await expect(page.getByRole('heading', { name: 'Queues & Agents' })).toBeVisible()
  const response = await optionsResponse
  const payload = (await response.json()) as {
    data: {
      capabilities: {
        configuration_available: boolean
        live_agent_controls_available: boolean
        statistics_available: boolean
      }
    }
  }

  expect(response.status()).toBe(200)
  expect(payload.data.capabilities).toEqual({
    configuration_available: true,
    live_agent_controls_available: false,
    statistics_available: false,
  })
  await expect(page.getByRole('button', { name: 'New queue' })).toBeEnabled()
  await expect(
    page.getByText(
      'Queue configuration is available, but the connected Switch did not report live agent controls as available.',
    ),
  ).toBeVisible()
  expect(JSON.stringify(payload.data.capabilities)).not.toContain('private')
  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('shows schema-backed Queue announcements with inline validation and bounded listboxes', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/queues')
  await expect(page.getByRole('heading', { name: 'Queues & Agents' })).toBeVisible()
  await page.getByRole('button', { name: 'New queue' }).click()
  await expect(page.getByRole('heading', { name: 'Create queue' })).toBeVisible()

  await page.getByRole('button', { name: 'Queue strategy' }).click()
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
  await page.getByRole('option', { name: 'Round robin' }).click()

  const formSections = page.getByRole('tablist', { name: 'Form sections' })
  await expect(formSections.getByRole('tab')).toHaveText(['Basic', 'Advanced'])
  await formSections.getByRole('tab', { name: 'Advanced' }).click()
  await expectControlRowAligned(
    page.getByLabel('Connection timeout'),
    page.getByLabel('Maximum callers'),
  )
  await page.getByRole('switch', { name: 'Periodic announcements' }).click()
  await expect(page.getByLabel('Announcement interval (seconds)')).toBeVisible()
  await expect(page.getByRole('button', { name: 'You are at position' })).toBeVisible()

  await page.getByRole('button', { name: 'Save queue' }).click()
  const name = page.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a queue name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('creates, edits, clears, and removes Queue announcement configuration', async ({ page }) => {
  const issues = collectPageIssues(page)
  const name = `E2E queue announcements ${Date.now()}`
  let createdId: string | null = null

  try {
    await page.goto('/queues')
    await page.getByRole('button', { name: 'New queue' }).click()
    await page.getByLabel('Name', { exact: true }).fill(name)
    await page.getByRole('tab', { name: 'Advanced' }).click()
    await page.getByLabel('Maximum priority').fill('12')
    await page.getByRole('switch', { name: 'Periodic announcements' }).click()
    await page.getByRole('switch', { name: 'Announce queue position' }).click()

    const creation = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/queues$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save queue' }).click()
    const createResponse = await creation
    expect(createResponse.status()).toBe(201)
    const created = (await createResponse.json()) as {
      data: {
        id: string
        max_priority: number
        announcements: { enabled: boolean; interval: number }
      }
    }
    createdId = created.data.id
    expect(created.data.max_priority).toBe(12)
    expect(created.data.announcements).toMatchObject({ enabled: true, interval: 30 })

    await expect(page.getByRole('heading', { name: 'Create queue' })).toHaveCount(0)
    await page.getByRole('cell', { name, exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Edit queue' })).toBeVisible()
    await page.getByRole('tab', { name: 'Advanced' }).click()
    await expect(page.getByLabel('Maximum priority')).toBeDisabled()
    await page.getByLabel('Announcement interval (seconds)').fill('45')
    const update = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/queues/${createdId}`),
    )
    await page.getByRole('button', { name: 'Save queue' }).click()
    const updateResponse = await update
    expect(updateResponse.status()).toBe(200)
    expect(
      ((await updateResponse.json()) as { data: { announcements: { interval: number } } }).data
        .announcements.interval,
    ).toBe(45)

    await page.getByRole('cell', { name, exact: true }).click()
    await page.getByRole('tab', { name: 'Advanced' }).click()
    await page.getByRole('switch', { name: 'Periodic announcements' }).click()
    const clearing = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/queues/${createdId}`),
    )
    await page.getByRole('button', { name: 'Save queue' }).click()
    const clearResponse = await clearing
    expect(clearResponse.status()).toBe(200)
    expect(
      ((await clearResponse.json()) as { data: { announcements: { enabled: boolean } } }).data
        .announcements.enabled,
    ).toBe(false)
  } finally {
    if (createdId !== null) {
      await page.goto('/queues')
      const row = page.getByRole('cell', { name, exact: true })

      if (await row.isVisible()) {
        await row.click()
        const deletion = page.waitForResponse(
          (response) =>
            response.request().method() === 'DELETE' &&
            new URL(response.url()).pathname.endsWith(`/queues/${createdId}`),
        )
        await page.getByRole('button', { name: 'Delete queue' }).click()
        await page.getByRole('dialog').getByRole('button', { name: 'Delete queue' }).click()
        expect((await deletion).status()).toBe(204)
      }
    }
  }

  expect(issues).toEqual([])
})
