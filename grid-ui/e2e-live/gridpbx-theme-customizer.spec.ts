import { expect, test } from '@playwright/test'

test('applies, persists, and resets accessible shell themes', async ({ page }, testInfo) => {
  const browserErrors: string[] = []

  page.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(message.text())
  })
  page.on('pageerror', (error) => browserErrors.push(error.message))
  await page.goto('/')
  await page.evaluate(() => window.localStorage.removeItem('gridpbx.shell-theme.v1'))
  await page.reload()
  await page.getByRole('button', { name: 'Customize theme' }).click()

  const panel = page.getByRole('dialog')
  await expect(panel.getByRole('heading', { name: 'Theme customizer' })).toBeVisible()
  const headerThemes = panel.getByRole('radiogroup', { name: 'Header color scheme' })
  const sidebarThemes = panel.getByRole('radiogroup', { name: 'Sidebar color scheme' })
  await expect(headerThemes).toBeVisible()
  await expect(sidebarThemes).toBeVisible()
  await expect(headerThemes.getByRole('radio')).toHaveCount(24)
  await expect(sidebarThemes.getByRole('radio')).toHaveCount(24)
  await expect(headerThemes.getByRole('radio', { name: 'Aurora header' })).toBeVisible()
  await expect(sidebarThemes.getByRole('radio', { name: 'Lavender sidebar' })).toBeVisible()

  await panel.getByRole('radio', { name: 'Midnight header' }).click()
  await panel.getByRole('radio', { name: 'Emerald sidebar' }).click()
  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'midnight')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'emerald')
  await expect
    .poll(() =>
      page.locator('.app-header').evaluate((element) => getComputedStyle(element).backgroundColor),
    )
    .toBe('rgb(23, 33, 58)')
  await expect
    .poll(() =>
      page
        .locator('aside.app-sidebar')
        .evaluate((element) => getComputedStyle(element).backgroundColor),
    )
    .toBe('rgb(23, 121, 91)')

  await page.screenshot({ path: testInfo.outputPath('theme-customizer.png') })
  await panel.getByRole('button', { name: 'Close theme customizer' }).click()
  await page.reload()

  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'midnight')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'emerald')

  await page.getByRole('button', { name: 'Customize theme' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Restore all defaults' }).click()
  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'light')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'light')
  expect(browserErrors).toEqual([])
})
