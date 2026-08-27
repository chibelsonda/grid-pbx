import { test, expect } from '@playwright/test'

test('shows the GridPBX dashboard shell', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Good morning, Admin' })).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Primary navigation' })).toBeVisible()
})
