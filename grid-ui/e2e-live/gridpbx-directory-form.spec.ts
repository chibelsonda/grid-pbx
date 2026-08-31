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

test('keeps Directory validation inline and its sort listbox inside the viewport', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/directories')
  await expect(page.getByRole('heading', { name: 'Directories' })).toBeVisible()
  await page.getByRole('button', { name: 'New directory' }).click()
  await expect(page.getByRole('heading', { name: 'Create directory' })).toBeVisible()

  await page.getByRole('tab', { name: 'Advanced' }).click()
  await page.getByRole('button', { name: 'Sort names by' }).click()
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

  await page.getByRole('button', { name: 'Save directory' }).click()
  const name = page.getByLabel('Name', { exact: true })
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a directory name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})
