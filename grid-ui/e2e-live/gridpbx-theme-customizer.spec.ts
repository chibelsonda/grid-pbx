import { expect, test } from '@playwright/test'

test('applies, persists, overrides, and resets coordinated application themes', async ({
  page,
}, testInfo) => {
  const browserErrors: string[] = []

  page.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(message.text())
  })
  page.on('pageerror', (error) => browserErrors.push(error.message))
  await page.goto('/')
  await page.evaluate(() => {
    window.localStorage.removeItem('gridpbx.shell-theme.v1')
    window.localStorage.removeItem('gridpbx.application-theme.v2')
  })
  await page.reload()
  await page.getByRole('button', { name: 'Customize theme' }).click()

  const panel = page.getByRole('dialog')
  await expect(panel.getByRole('heading', { name: 'Theme customizer' })).toBeVisible()
  const applicationThemes = panel.getByRole('radiogroup', { name: 'Application color scheme' })
  await expect(applicationThemes).toBeVisible()
  await expect(applicationThemes.getByRole('radio')).toHaveCount(12)
  await applicationThemes.getByRole('radio', { name: 'Ocean application theme' }).click()

  await expect(page.locator('.app-workspace')).toHaveAttribute('data-application-theme', 'ocean')
  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'ocean')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'navy')
  await expect
    .poll(() =>
      page.locator('.app-header').evaluate((element) => getComputedStyle(element).backgroundColor),
    )
    .toBe('rgb(8, 126, 164)')
  await expect
    .poll(() =>
      page
        .locator('aside.app-sidebar')
        .evaluate((element) => getComputedStyle(element).backgroundColor),
    )
    .toBe('rgb(18, 52, 91)')

  await page.screenshot({ path: testInfo.outputPath('theme-customizer-presets.png') })

  await panel.getByRole('button', { name: 'Advanced overrides' }).click()
  const headerThemes = panel.getByRole('radiogroup', { name: 'Header color scheme' })
  const sidebarThemes = panel.getByRole('radiogroup', { name: 'Sidebar color scheme' })
  await expect(headerThemes.getByRole('radio')).toHaveCount(24)
  await expect(sidebarThemes.getByRole('radio')).toHaveCount(24)
  await headerThemes.getByRole('radio', { name: 'Midnight header' }).click()
  await sidebarThemes.getByRole('radio', { name: 'Emerald sidebar' }).click()
  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'midnight')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'emerald')

  await page.screenshot({ path: testInfo.outputPath('theme-customizer.png') })
  await panel.getByRole('button', { name: 'Close theme customizer' }).click()
  await page.reload()

  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'midnight')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'emerald')
  await expect(page.locator('.app-workspace')).toHaveAttribute('data-application-theme', 'ocean')

  await page.getByRole('button', { name: 'Customize theme' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Restore all defaults' }).click()
  await expect(page.locator('.app-header')).toHaveAttribute('data-theme', 'light')
  await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-theme', 'light')
  expect(browserErrors).toEqual([])
})
