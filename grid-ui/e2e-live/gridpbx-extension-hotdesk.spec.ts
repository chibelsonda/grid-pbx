import { expect, test } from '@playwright/test'

test('shows and validates login credentials and hotdesk in the Extension slide-over', async ({
  page,
}) => {
  await page.goto('/extensions')
  await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
  await page.getByRole('button', { name: 'Create extension' }).click()

  const credentials = page.locator('article').filter({ hasText: 'Switch portal login' })
  const username = credentials.getByRole('textbox', { name: 'Login username' })
  await username.fill('alice.operator')
  const password = credentials.getByLabel('Password', { exact: true })
  const confirmation = credentials.getByLabel('Confirm password')
  await password.fill('short')
  await confirmation.fill('different-password')

  const hotdesk = page.locator('article').filter({ hasText: 'Hotdesk profile' })
  await expect(hotdesk).toBeVisible()
  await hotdesk.getByRole('switch', { name: 'Enabled' }).click()
  await expect(hotdesk.getByRole('textbox', { name: 'Hotdesk ID' })).toBeVisible()
  await expect(hotdesk.getByRole('switch', { name: 'Keep logged in elsewhere' })).toBeVisible()
  await hotdesk.getByRole('switch', { name: 'Require a PIN' }).click()
  await expect(hotdesk.getByText('Hotdesk PIN', { exact: true })).toBeVisible()

  const hotdeskId = hotdesk.getByRole('textbox', { name: 'Hotdesk ID' })
  await hotdeskId.fill('abc')
  await page.getByRole('button', { name: 'Create extension', exact: true }).last().click()

  await expect(credentials.getByText('Use at least 6 characters.')).toBeVisible()
  await expect(credentials.getByText('Passwords do not match.')).toBeVisible()
  await expect(credentials.locator('input[type="password"]').first()).toHaveClass(/border-red-400/)
  await expect(hotdesk.getByText('Use 4–15 dial-pad characters.')).toBeVisible()
  await expect(hotdeskId).toHaveClass(/border-red-400/)
  await expect(
    hotdesk.getByText('Enter a hotdesk PIN when PIN protection is enabled.'),
  ).toBeVisible()
})
