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

async function expectInsideViewport(
  page: Page,
  locator: ReturnType<Page['locator']>,
): Promise<void> {
  const bounds = await locator.boundingBox()
  expect(bounds).not.toBeNull()
  expect(bounds!.x).toBeGreaterThanOrEqual(0)
  expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390)
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
        agent_statistics_available: boolean
        statistics_available: boolean
      }
    }
  }

  expect(response.status()).toBe(200)
  expect(payload.data.capabilities).toEqual({
    configuration_available: true,
    live_agent_controls_available: false,
    agent_statistics_available: false,
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

test('polls an open live agent panel and stops after it closes', async ({ page }) => {
  const issues = collectPageIssues(page)
  const agentId = '11111111-1111-4111-8111-111111111111'
  let statusFetches = 0

  await page.route(/\/api\/v1\/accounts\/[^/]+\/queues\/options$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          agents: [],
          media: [],
          capabilities: {
            configuration_available: true,
            live_agent_controls_available: true,
            agent_statistics_available: false,
            statistics_available: false,
          },
        },
      }),
    })
  })
  await page.route(/\/api\/v1\/accounts\/[^/]+\/agents$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: agentId,
            name: 'Isolated Agent',
            extension: '1001',
            queues: [{ id: '22222222-2222-4222-8222-222222222222', name: 'Support' }],
          },
        ],
      }),
    })
  })
  await page.route(
    new RegExp(`/api/v1/accounts/[^/]+/agents/${agentId}/status$`),
    async (route) => {
      statusFetches += 1
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { id: agentId, status: 'connected', timestamp: 63800000000 },
        }),
      })
    },
  )

  await page.goto('/queues')
  await page.getByRole('tab', { name: 'Agents' }).click()
  await page.getByRole('button', { name: 'Isolated Agent' }).click()
  const dialog = page.getByRole('dialog', { name: 'Agent status' })
  const panel = dialog.getByTestId('slide-over-panel')

  await expect(panel).toBeVisible()
  await expect(panel.getByText('connected')).toBeVisible()
  await expect(panel.getByText('Auto-refresh · 5s', { exact: false })).toBeVisible()
  await expect.poll(() => statusFetches, { timeout: 7_000 }).toBeGreaterThanOrEqual(2)

  await dialog.getByRole('button', { name: 'Close panel' }).click()
  const fetchesAfterClose = statusFetches
  await page.waitForTimeout(5_500)

  expect(statusFetches).toBe(fetchesAfterClose)
  expect(issues).toEqual([])
})

test('shows and refreshes privacy-safe live Queue activity when the capability is available', async ({
  page,
}) => {
  test.setTimeout(35_000)
  const issues = collectPageIssues(page)
  let statisticsFetches = 0

  await page.route(/\/api\/v1\/accounts\/[^/]+\/queues\/options$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          agents: [],
          media: [],
          capabilities: {
            configuration_available: true,
            live_agent_controls_available: true,
            agent_statistics_available: false,
            statistics_available: true,
          },
        },
      }),
    })
  })
  await page.route(/\/api\/v1\/accounts\/[^/]+\/queues\/statistics$/, async (route) => {
    statisticsFetches += 1
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          observed_at: '2026-09-01T04:05:06+00:00',
          totals: {
            waiting: statisticsFetches,
            handled: 1,
            abandoned: 2,
            processed: 4,
            average_wait_seconds: 65,
            average_talk_seconds: 120,
            longest_current_wait_seconds: 130,
          },
          queues: [
            {
              id: '11111111-1111-4111-8111-111111111111',
              name: 'Isolated Support',
              waiting: statisticsFetches,
              handled: 1,
              abandoned: 2,
              processed: 4,
              average_wait_seconds: 65,
              average_talk_seconds: 120,
              longest_current_wait_seconds: 130,
            },
          ],
          unresolved_records: 0,
        },
      }),
    })
  })

  await page.goto('/queues')
  const activity = page.getByRole('region', { name: 'Live queue activity' })

  await expect(activity).toBeVisible()
  await expect(activity.getByText('Isolated Support')).toBeVisible()
  await expect(activity.getByRole('cell', { name: '1m 5s' })).toBeVisible()
  await expect.poll(() => statisticsFetches).toBe(1)
  await activity.getByRole('button', { name: 'Refresh' }).click()
  await expect.poll(() => statisticsFetches).toBe(2)
  await expect.poll(() => statisticsFetches, { timeout: 17_000 }).toBeGreaterThanOrEqual(3)

  expect(await activity.textContent()).not.toContain('caller_id')
  expect(await activity.textContent()).not.toContain('agent_id')
  expect(issues).toEqual([])
})

test('shows and refreshes privacy-safe agent performance on the Agents tab', async ({ page }) => {
  test.setTimeout(35_000)
  const issues = collectPageIssues(page)
  let statisticsFetches = 0

  await page.route(/\/api\/v1\/accounts\/[^/]+\/queues\/options$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          agents: [],
          media: [],
          capabilities: {
            configuration_available: true,
            live_agent_controls_available: true,
            agent_statistics_available: true,
            statistics_available: false,
          },
        },
      }),
    })
  })
  await page.route(/\/api\/v1\/accounts\/[^/]+\/agents\/statistics$/, async (route) => {
    statisticsFetches += 1
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          observed_at: '2026-09-01T04:05:06+00:00',
          totals: {
            total_calls: 12,
            answered_calls: 9,
            missed_calls: 3,
            answer_rate_percentage: 75,
          },
          agents: [
            {
              id: '11111111-1111-4111-8111-111111111111',
              name: 'Isolated Agent',
              extension: '1001',
              total_calls: 10,
              answered_calls: 8,
              missed_calls: 2,
              answer_rate_percentage: 80,
            },
          ],
          unresolved_agents: 0,
        },
      }),
    })
  })

  await page.goto('/queues')
  await page.getByRole('tab', { name: 'Agents' }).click()
  const performance = page.getByRole('region', { name: 'Live agent performance' })

  await expect(performance).toBeVisible()
  await expect(performance.getByText('Isolated Agent')).toBeVisible()
  await expect(performance.getByRole('cell', { name: '80%' })).toBeVisible()
  await expect.poll(() => statisticsFetches).toBe(1)
  await performance.getByRole('button', { name: 'Refresh' }).click()
  await expect.poll(() => statisticsFetches).toBe(2)
  await expect.poll(() => statisticsFetches, { timeout: 17_000 }).toBeGreaterThanOrEqual(3)

  expect(await performance.textContent()).not.toContain('agent_id')
  expect(await performance.textContent()).not.toContain('queue_id')
  expect(issues).toEqual([])
})

test('keeps Queue inventories and the schema-backed form accessible on mobile', async ({
  page,
}, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []

  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:queues|agents|sync\/queues)(?:\/|$)/.test(path)
    ) {
      mutations.push(`${request.method()} ${path}`)
    }
  })

  await page.goto('/queues')
  await expect(page.getByRole('heading', { name: 'Queues & Agents' })).toBeVisible()
  const queueTable = page.getByRole('table', { name: 'Queues for the selected Switch account' })
  await expect(queueTable).toBeVisible()
  await expect(queueTable.getByRole('columnheader')).toHaveCount(5)
  await expect(queueTable).toHaveAttribute('aria-busy', 'false')

  await page.getByRole('button', { name: 'New queue' }).click()
  await expect(page.getByRole('heading', { name: 'Create queue' })).toBeVisible()
  const dialog = page.getByRole('dialog', { name: 'Create queue' })

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
  const name = dialog.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a queue name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  const basicTab = dialog.getByRole('tab', { name: 'Basic' })
  await expect(basicTab).toHaveAttribute('aria-selected', 'true')
  await expect(basicTab).toHaveClass(/border-brand-500/)
  await expect(dialog.getByRole('group', { name: 'Agent roster' })).toBeVisible()

  await page.setViewportSize({ width: 390, height: 844 })
  await page.waitForTimeout(350)
  await expectInsideViewport(page, dialog.getByTestId('slide-over-panel'))
  await expectInsideViewport(page, dialog.getByRole('button', { name: 'Close panel' }))
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('queue-create-mobile-validation.png'),
    fullPage: true,
  })

  await dialog.getByRole('button', { name: 'Cancel' }).click()
  await expect(page.getByRole('heading', { name: 'Create queue' })).toHaveCount(0)
  for (const action of [
    page.getByRole('button', { name: 'Sync', exact: true }),
    page.getByRole('button', { name: 'New queue' }),
    page.getByRole('button', { name: 'Search', exact: true }),
  ]) {
    await expect(action).toBeVisible()
    await expectInsideViewport(page, action)
  }

  await page.getByRole('tab', { name: 'Agents' }).click()
  const agentTable = page.getByRole('table', {
    name: 'Queue agents for the selected Switch account',
  })
  await expect(agentTable).toBeVisible()
  await expect(agentTable.getByRole('columnheader')).toHaveCount(4)
  await expect(agentTable).toHaveAttribute('aria-busy', 'false')

  expect(mutations).toEqual([])
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
    await page.getByRole('button', { name, exact: true }).click()
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

    await page.getByRole('button', { name, exact: true }).click()
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
      const row = page.getByRole('button', { name, exact: true })

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
