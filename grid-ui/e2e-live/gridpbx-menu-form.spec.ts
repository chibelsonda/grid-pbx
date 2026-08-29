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

  const timeout = page.getByLabel('Initial digit timeout (ms)')
  await timeout.fill('60001')
  await page.getByRole('button', { name: 'Save menu' }).click()

  const name = page.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(timeout).toHaveAttribute('aria-invalid', 'true')
  await expect(timeout).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a menu name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

