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

test('uses non-clipping Fax choices and shared inline validation', async ({ page }) => {
  const issues = collectPageIssues(page)
  const emptyPage = {
    data: [],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  }
  await page.route('**/api/v1/accounts/*/fax-boxes?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(emptyPage),
    }),
  )
  await page.route('**/api/v1/accounts/*/faxes?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(emptyPage),
    }),
  )
  await page.route('**/api/v1/accounts/*/fax-boxes/options', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          account_defaults: { timezone: 'Asia/Manila' },
          timezones: ['Asia/Manila', 'UTC'],
          caller_id_numbers: ['+12025550100'],
          owners: [
            {
              id: '16f95ac5-243c-476a-b238-9f51108f82e1',
              label: 'Reception',
              detail: '1000',
            },
          ],
        },
      }),
    }),
  )

  await page.goto('/faxes')
  await expect(page.getByRole('heading', { name: 'Fax boxes & history' })).toBeVisible()
  await page.getByRole('button', { name: 'New fax box' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create fax box' })

  await dialog.getByRole('button', { name: 'Fax-box owner' }).click()
  const listbox = page.getByRole('listbox')
  const box = await listbox.boundingBox()
  const viewport = page.viewportSize()
  expect(box).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(box!.x).toBeGreaterThanOrEqual(0)
  expect(box!.y).toBeGreaterThanOrEqual(0)
  expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
  await page.getByRole('option', { name: 'No owner' }).click()

  await dialog.getByLabel('Fax retries').fill('5')
  await dialog.getByLabel('Inbound notification emails').fill('invalid')
  await dialog.getByRole('button', { name: 'Save fax box' }).click()
  for (const control of [
    dialog.getByLabel('Fax-box name'),
    dialog.getByLabel('Fax retries'),
    dialog.getByLabel('Inbound notification emails'),
  ]) {
    await expect(control).toHaveAttribute('aria-invalid', 'true')
    await expect(control).toHaveClass(/border-red-400/)
  }
  await expect(dialog.getByText('Enter a fax-box name.')).toBeVisible()
  await expect(dialog.getByText('Enter a valid email address.')).toBeVisible()
  await expect(dialog.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})
