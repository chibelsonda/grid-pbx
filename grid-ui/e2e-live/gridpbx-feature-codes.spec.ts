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

test('shows an accessible account feature-code inventory without mutations or raw IDs', async ({
  page,
}, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\//.test(new URL(request.url()).pathname)
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })
  const responsePromise = page.waitForResponse((response) => {
    const url = new URL(response.url())

    return (
      /\/api\/v1\/accounts\/[^/]+\/callflows$/.test(url.pathname) &&
      url.searchParams.get('type') === 'feature_code'
    )
  })

  await page.goto('/feature-codes')
  await expect(page.getByRole('heading', { name: 'Feature Codes' })).toBeVisible()
  const response = await responsePromise
  const payload = (await response.json()) as {
    data: Array<{
      id: string
      route_type: string
      feature_code: { name: string | null; number: string | null }
      root_module: string | null
    }>
    meta: { total: number }
  }

  expect(response.status()).toBe(200)
  expect(payload.meta.total).toBe(17)
  expect(payload.data).toHaveLength(17)
  expect(payload.data.every(({ route_type }) => route_type === 'feature_code')).toBe(true)
  expect(payload.data.every(({ id }) => /^[0-9a-f-]{36}$/i.test(id))).toBe(true)
  expect(JSON.stringify(payload)).not.toMatch(
    /callflow_id|switch_resource_id|owner_switch_resource_id|switch_account_id|switch_json|raw-runtime-id/i,
  )

  await expect(page.getByText('Read-only inventory.')).toBeVisible()
  await expect(page.getByText('Mutations gated')).toBeVisible()
  await expect(page.getByText('17', { exact: true })).toBeVisible()
  await expect(page.getByText('Hotdesk Login')).toBeVisible()
  await expect(page.getByText('*11', { exact: true })).toBeVisible()
  await expect(page.getByText('Voicemail Check')).toBeVisible()

  const table = page.getByRole('table', {
    name: 'Active feature codes projected for the selected Switch account',
  })
  await expect(table).toBeVisible()
  await expect(table.getByRole('columnheader')).toHaveCount(6)
  await expect(table).toHaveAttribute('aria-busy', 'false')

  const search = page.getByLabel('Search feature codes')
  await search.focus()
  await expect(search).toBeFocused()
  await page.keyboard.type('hotdesk')
  await expect(page.getByText('Hotdesk Login')).toBeVisible()
  await expect(page.getByText('Hotdesk Logout')).toBeVisible()
  await expect(page.getByText('Hotdesk Toggle')).toBeVisible()
  await expect(page.getByText('Call Forward Activate')).toBeHidden()

  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('heading', { name: 'Feature Codes' })).toBeVisible()
  await expect(page.getByText('Read-only inventory.')).toBeVisible()
  await expectInsideViewport(page, page.getByRole('button', { name: 'Refresh' }))
  await expectInsideViewport(page, page.getByLabel('Search feature codes'))
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('feature-codes-mobile.png'),
    fullPage: true,
  })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
