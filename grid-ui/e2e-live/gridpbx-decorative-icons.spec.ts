import { expect, test } from '@playwright/test'

test('renders decorative icons without badge containers', async ({ page }) => {
  const browserErrors: string[] = []

  page.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(message.text())
  })
  page.on('pageerror', (error) => browserErrors.push(error.message))

  await page.goto('/call-routing')
  await expect(page.getByRole('heading', { name: 'Callflows' })).toBeVisible()

  const decorativeIcons = page.locator(
    "span.grid[class*='place-items-center'][class*='bg-']:has(> svg:only-child):not([class*='absolute']):not(.sidebar-accent-bg)",
  )
  await expect.poll(() => decorativeIcons.count()).toBeGreaterThan(0)

  const displayValues = await decorativeIcons.evaluateAll((elements) =>
    elements.map((element) => window.getComputedStyle(element).display),
  )

  expect(new Set(displayValues)).toEqual(new Set(['contents']))
  expect(browserErrors).toEqual([])
})
