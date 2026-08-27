import { test, expect } from '@playwright/test'

test('signs in and shows the GridPBX dashboard shell', async ({ page }) => {
  await page.route('**/api/v1/session', (route) =>
    route.fulfill({ status: 401, contentType: 'application/json', body: '{"message":"Unauthenticated."}' }),
  )
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/login', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { user: { id: 1, name: 'Grid Admin', email: 'admin@gridpbx.local' } } }),
    }),
  )
  await page.route('**/api/v1/accounts', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    }),
  )

  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible()
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page.getByRole('heading', { name: 'Good day, Grid' })).toBeVisible()
  await expect(page.getByText('People & Extensions')).toBeVisible()
})
