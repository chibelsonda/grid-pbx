import { expect, test } from '@playwright/test'
import { deviceParityMatrix } from './deviceMatrix.js'

test('matches the expected Advanced tab matrix without using the desktop pointer', async ({
  page,
}) => {
  await page.goto('/devices')
  await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()
  await page.getByRole('link', { name: 'Add device' }).click()

  await expect(page.getByRole('heading', { name: 'Add device' })).toBeVisible()
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()

  for (const device of deviceParityMatrix) {
    await page.getByRole('button', { name: new RegExp(`^${device.gridLabel}`) }).click()

    const advancedTabs = page.getByRole('tablist').nth(1).getByRole('tab')
    await expect(advancedTabs).toHaveText(device.gridTabs ?? device.tabs)
    await expect(
      page.getByRole('button', { name: new RegExp(`^${device.gridLabel}`) }),
    ).toHaveAttribute('aria-pressed', 'true')
  }
})

test('reorders codec priority without using the desktop pointer', async ({ page }) => {
  await page.goto('/devices')
  await page.getByRole('link', { name: 'Add device' }).click()
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'Audio', exact: true }).click()

  const priorities = page
    .getByRole('list', { name: 'Audio codec priority selected codecs' })
    .locator('[data-codec-value]')

  await expect(priorities).toHaveText(['PCMU', 'PCMA'])
  await page.getByRole('button', { name: 'Move PCMA up' }).click()
  await expect(priorities).toHaveText(['PCMA', 'PCMU'])
})

test('exposes schema-backed routing editors in Device Options', async ({ page }) => {
  await page.goto('/devices')
  await page.getByRole('link', { name: 'Add device' }).click()
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'Options', exact: true }).click()

  await expect(page.getByRole('button', { name: 'Metaflows and hotdesk' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Custom SIP headers' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Dial plan' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'General flags and formatters' })).toBeVisible()
})

test('shows the dedicated hotdesk panel on an existing device', async ({ page }) => {
  await page.goto('/devices')
  const deviceLink = page.locator('tbody a').first()
  await expect(deviceLink).toBeVisible()
  await deviceLink.click()

  await expect(page.getByRole('heading', { name: 'Active hotdesk users' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Hotdesk extension' })).toBeVisible()
  await expect(page.getByText(/without exposing Switch IDs/)).toBeVisible()
})

test('keeps the final restriction menu visible inside the viewport', async ({ page }) => {
  await page.goto('/devices')
  await page.getByRole('link', { name: 'Add device' }).click()
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'Restrictions', exact: true }).click()

  const restrictionButtons = page.getByRole('button', { name: /^Restriction for / })
  const finalRestriction = restrictionButtons.last()
  await finalRestriction.click()

  const options = page.getByRole('listbox')
  await expect(options).toBeVisible()

  const [optionsBox, viewport] = await Promise.all([
    options.boundingBox(),
    Promise.resolve(page.viewportSize()),
  ])

  expect(optionsBox).not.toBeNull()
  expect(viewport).not.toBeNull()
  await expect(options).toHaveCSS('position', 'fixed')
  expect(optionsBox!.x).toBeGreaterThanOrEqual(0)
  expect(optionsBox!.y).toBeGreaterThanOrEqual(0)
  expect(optionsBox!.x + optionsBox!.width).toBeLessThanOrEqual(viewport!.width)
  expect(optionsBox!.y + optionsBox!.height).toBeLessThanOrEqual(viewport!.height)
})

test('uses the connected Device schema and reports provisioning discovery state', async ({
  page,
}) => {
  await page.goto('/devices')
  const optionsResponse = page.waitForResponse(
    (response) => response.url().includes('/devices/options') && response.status() === 200,
  )
  await page.getByRole('link', { name: 'Add device' }).click()

  const options = (await (await optionsResponse).json()).data
  expect(options.device_schema.source).toBe('connected_switch')
  expect(options.device_schema.call_forward.number_max_length).toBe(15)
  expect(options.device_schema.sip.proxy).toBe(false)
  expect(options.device_schema.provision.check_sync_event).toBe(true)
  expect(options.provisioning_catalog.available).toBe(true)
  expect(options.provisioning_catalog.brands).toEqual(
    expect.arrayContaining([expect.objectContaining({ id: 'yealink', name: 'Yealink' })]),
  )

  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'SIP', exact: true }).click()
  await expect(page.getByText('SIP proxy', { exact: true })).toHaveCount(0)
  await expect(page.getByText('Strip leading +', { exact: true })).toHaveCount(0)

  await page.getByRole('tab', { name: 'Options', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Provisioning events' })).toBeVisible()
})

test('shows notify when unregistered in Basic without duplicating it in Advanced Options', async ({
  page,
}) => {
  await page.goto('/devices')
  await page.getByRole('link', { name: 'Add device' }).click()

  await expect(page.getByRole('switch', { name: 'Notify when unregistered' })).toBeVisible()

  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'Options', exact: true }).click()

  await expect(page.getByRole('switch', { name: 'Notify when unregistered' })).toHaveCount(0)
  await expect(page.getByRole('switch', { name: 'Unregistration notifications' })).toHaveCount(0)
})

test('discovers and selects a provisioner brand, family, and model', async ({ page }) => {
  await page.goto('/devices')
  await page.getByRole('link', { name: 'Add device' }).click()

  const brandButton = page.getByRole('button', { name: 'Select device brand' })
  await brandButton.click()

  const brandMenu = page.getByRole('listbox')
  await expect(brandMenu).toBeVisible()
  await expect(brandMenu).toHaveCSS('position', 'fixed')

  const [menuBox, viewport] = await Promise.all([
    brandMenu.boundingBox(),
    Promise.resolve(page.viewportSize()),
  ])

  expect(menuBox).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(menuBox!.x).toBeGreaterThanOrEqual(0)
  expect(menuBox!.y).toBeGreaterThanOrEqual(0)
  expect(menuBox!.x + menuBox!.width).toBeLessThanOrEqual(viewport!.width)
  expect(menuBox!.y + menuBox!.height).toBeLessThanOrEqual(viewport!.height)

  const finalBrand = brandMenu.getByRole('option').last()
  await finalBrand.scrollIntoViewIfNeeded()
  await expect(finalBrand).toBeVisible()
  await page.getByRole('option', { name: 'Yealink' }).click()

  await page.getByRole('button', { name: 'Select device family' }).click()
  await page.getByRole('option', { name: 'T5 Series' }).click()

  await page.getByRole('button', { name: 'Select device model' }).click()
  await page.getByRole('option', { name: 'T54W' }).click()

  await expect(page.getByRole('button', { name: 'Select device brand' })).toContainText('Yealink')
  await expect(page.getByRole('button', { name: 'Select device family' })).toContainText(
    'T5 Series',
  )
  await expect(page.getByRole('button', { name: 'Select device model' })).toContainText('T54W')
})

test('matches the minimal SIP URI Options workflow', async ({ page }) => {
  await page.goto('/devices')
  await page.getByRole('link', { name: 'Add device' }).click()
  await page.getByRole('button', { name: /^SIP URI/ }).click()
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'Options', exact: true }).click()

  await expect(page.getByRole('switch', { name: 'Hide from contact list' })).toBeVisible()
  await expect(page.getByRole('switch', { name: 'Call waiting' })).toHaveCount(0)
  await expect(page.getByRole('switch', { name: 'Do not disturb' })).toHaveCount(0)
  await expect(page.getByText('Presence ID', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Routing and endpoint behavior' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Custom SIP headers' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Dial plan' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Metaflows and hotdesk' })).toHaveCount(0)
})

test('matches Cellphone and Landline forwarding workflows', async ({ page }) => {
  for (const label of ['Cell phone', 'Landline']) {
    await page.goto('/devices')
    await page.getByRole('link', { name: 'Add device' }).click()
    await page.getByRole('button', { name: new RegExp(`^${label}`) }).click()

    await expect(page.getByRole('switch', { name: 'Enabled' })).toBeVisible()
    await expect(page.getByText('Destination number', { exact: true })).toBeVisible()

    await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
    await page.getByRole('tab', { name: 'Options', exact: true }).click()

    await expect(page.getByRole('switch', { name: 'Require keypress' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Keep original caller ID' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Hide from contact list' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Enable call forwarding' })).toHaveCount(0)
    await expect(page.getByText('Forwarding number', { exact: true })).toHaveCount(0)
    await page.getByRole('button', { name: /Advanced forwarding/ }).click()
    await expect(page.getByRole('switch', { name: 'Direct calls only' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Forward only when offline' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Ignore early media' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Replace this device' })).toBeVisible()
  }
})

test('matches registered-endpoint T.38 and completed-elsewhere capabilities', async ({ page }) => {
  const endpoints = [
    {
      label: 'VoIP phone',
      faxOption: true,
      completedElsewhere: true,
      tabs: ['Caller ID', 'Audio', 'Video'],
    },
    {
      label: 'Smartphone',
      faxOption: false,
      completedElsewhere: false,
      tabs: ['Caller ID', 'Audio', 'Video'],
    },
    {
      label: 'Softphone',
      faxOption: true,
      completedElsewhere: true,
      tabs: ['Caller ID', 'Audio', 'Video'],
    },
    {
      label: 'Fax',
      faxOption: true,
      completedElsewhere: false,
      tabs: ['Caller ID', 'Audio'],
    },
    {
      label: 'ATA',
      faxOption: true,
      completedElsewhere: false,
      tabs: ['Caller ID', 'Audio'],
    },
  ]

  for (const endpoint of endpoints) {
    await page.goto('/devices')
    await page.getByRole('link', { name: 'Add device' }).click()
    await page.getByRole('button', { name: new RegExp(`^${endpoint.label}`) }).click()
    await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
    for (const tab of endpoint.tabs) {
      await expect(page.getByRole('tab', { name: tab, exact: true })).toBeVisible()
    }
    await page.getByRole('tab', { name: 'Options', exact: true }).click()

    await expect(page.getByRole('switch', { name: 'Enable T.38 fax' })).toHaveCount(
      endpoint.faxOption ? 1 : 0,
    )
    await expect(page.getByRole('switch', { name: 'Ignore completed elsewhere' })).toHaveCount(
      endpoint.completedElsewhere ? 1 : 0,
    )

    await page
      .getByRole('tab', { name: endpoint.label === 'Smartphone' ? 'Wi-Fi calling' : 'SIP' })
      .click()
    await expect(page.getByRole('switch', { name: 'Ignore completed elsewhere' })).toHaveCount(0)
  }
})
