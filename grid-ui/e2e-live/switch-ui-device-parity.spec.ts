import process from 'node:process'
import { expect, test } from '@playwright/test'
import { deviceParityMatrix, normalizeSwitchTab } from './deviceMatrix.js'

test('provides the reference Device tab matrix in the isolated Switch UI session', async ({
  page,
}) => {
  test.skip(
    !process.env.SWITCH_E2E_USERNAME ||
      !process.env.SWITCH_E2E_PASSWORD ||
      !process.env.SWITCH_E2E_ACCOUNT_NAME,
    'Set SWITCH_E2E_USERNAME, SWITCH_E2E_PASSWORD, and SWITCH_E2E_ACCOUNT_NAME.',
  )

  await page.goto('/#/apps/callflows')
  await page.getByText('Device', { exact: true }).click()
  await page.getByText('Add', { exact: true }).click()

  for (const device of deviceParityMatrix) {
    await page.locator(`.media_tabs .buttons[device_type="${device.switchType}"]`).click()
    await page.locator('.view-buttons .advanced').click()

    const labels = await page.locator('ul.tabs:visible > li > a').allTextContents()
    expect(labels.map(normalizeSwitchTab)).toEqual(device.tabs)
  }
})
