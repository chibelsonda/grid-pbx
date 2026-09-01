import { expect, test } from '@playwright/test'

test.use({ viewport: { width: 1440, height: 900 } })

const liveLogoAccountId = process.env.GRID_E2E_ORGANIZATION_LOGO_ACCOUNT_ID
const liveLogoPath = process.env.GRID_E2E_ORGANIZATION_LOGO_PATH

test('keeps personal settings honest, persistent, and responsive', async ({ page }, testInfo) => {
  await page.goto('/settings')

  await expect(page.getByRole('heading', { name: 'Settings', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Appearance', includeHidden: true })).toBeHidden()
  await expect(
    page.getByRole('heading', { name: 'Workspace preferences', includeHidden: true }),
  ).toBeHidden()
  await expect(
    page.getByRole('heading', { name: 'Access and security', includeHidden: true }),
  ).toBeHidden()
  await expect(
    page.getByRole('heading', { name: 'Administration', includeHidden: true }),
  ).toBeHidden()
  const settingsNavigation = page.getByRole('navigation', { name: 'Settings sections' })
  await expect(settingsNavigation).toBeVisible()
  await expect(settingsNavigation.getByRole('tab')).toHaveText([
    'Profile',
    'Branding',
    'Appearance',
    'Workspace',
    'Administration',
    'Access & security',
  ])
  const desktopNavigationBox = await settingsNavigation.boundingBox()
  const desktopProfileBox = await page.locator('#profile').boundingBox()
  expect(desktopNavigationBox).not.toBeNull()
  expect(desktopProfileBox).not.toBeNull()
  expect(desktopNavigationBox!.x + desktopNavigationBox!.width).toBeLessThan(desktopProfileBox!.x)
  expect(desktopNavigationBox!.width).toBeGreaterThanOrEqual(235)
  expect(desktopNavigationBox!.width).toBeLessThanOrEqual(245)
  await settingsNavigation.getByRole('tab', { name: 'Access & security' }).click()
  await expect(page).toHaveURL(/\/settings#access-security$/)
  await expect(page.getByRole('heading', { name: 'Access and security' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Profile', includeHidden: true })).toBeHidden()
  await settingsNavigation.getByRole('tab', { name: 'Administration' }).click()
  await expect(page).toHaveURL(/\/settings#administration$/)
  await expect(page.getByRole('heading', { name: 'Administration' })).toBeVisible()
  await expect(page.getByRole('link', { name: /Account configuration/ })).toBeVisible()
  await settingsNavigation.getByRole('tab', { name: 'Profile', exact: true }).click()
  await expect(page).toHaveURL(/\/settings#profile$/)
  await expect(
    settingsNavigation.getByRole('tab', { name: 'Profile', exact: true }),
  ).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible()
  await expect(page.getByText('Scheduled for a later slice', { exact: false })).toHaveCount(0)

  let profileRequestCount = 0
  await page.route('**/api/v1/profile', async (route) => {
    profileRequestCount += 1
    expect(route.request().method()).toBe('PATCH')
    expect(route.request().postDataJSON()).toEqual({
      name: profileRequestCount === 1 ? 'E2E Profile Preview' : 'Rejected Profile Preview',
    })

    if (profileRequestCount === 2) {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The given data was invalid.',
          errors: { name: ['This preview name was rejected.'] },
        }),
      })
      return
    }

    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 'a346c2a8-89d5-4f55-a0bd-43c366746115',
            name: 'E2E Profile Preview',
            email: 'preview@example.test',
          },
        },
      }),
    })
  })
  await page.getByRole('button', { name: 'Edit display name' }).click()
  await page.getByRole('textbox', { name: 'Display name' }).fill('E2E Profile Preview')
  await page.getByRole('button', { name: 'Save name' }).click()
  await expect(page.getByText('E2E Profile Preview', { exact: true }).first()).toBeVisible()
  const globalNotification = page.getByTestId('global-notification')
  await expect(globalNotification).toContainText('Update successful')
  await expect(globalNotification).toContainText('The changes were saved successfully.')
  await globalNotification.getByRole('button', { name: 'Dismiss notification' }).click()

  await page.getByRole('button', { name: 'Edit display name' }).click()
  await page.getByRole('textbox', { name: 'Display name' }).fill('Rejected Profile Preview')
  await page.getByRole('button', { name: 'Save name' }).click()
  await expect(page.getByText('This preview name was rejected.')).toBeVisible()
  await expect(globalNotification).toContainText('Update failed')
  await expect(globalNotification).toContainText(
    'The changes could not be saved. Review the form or try again.',
  )
  await expect(globalNotification).toHaveAttribute('role', 'alert')
  await globalNotification.getByRole('button', { name: 'Dismiss notification' }).click()
  await page.getByRole('button', { name: 'Cancel' }).click()

  await settingsNavigation.getByRole('tab', { name: 'Appearance' }).click()
  await expect(page.getByText('Stored only in this browser', { exact: false })).toBeVisible()
  await page.getByRole('button', { name: 'Customize appearance' }).click()
  await expect(page.getByRole('heading', { name: 'Theme customizer' })).toBeVisible()
  await page.getByRole('button', { name: 'Close theme customizer' }).click()

  await settingsNavigation.getByRole('tab', { name: 'Workspace' }).click()
  const compactSidebar = page.getByRole('switch', { name: 'Use compact desktop sidebar' })
  const initialValue = await compactSidebar.getAttribute('aria-checked')
  await compactSidebar.click()
  await expect(compactSidebar).toHaveAttribute(
    'aria-checked',
    initialValue === 'true' ? 'false' : 'true',
  )
  await page.reload()
  await expect(compactSidebar).toHaveAttribute(
    'aria-checked',
    initialValue === 'true' ? 'false' : 'true',
  )
  await compactSidebar.click()
  await expect(compactSidebar).toHaveAttribute('aria-checked', initialValue ?? 'false')

  await settingsNavigation.getByRole('tab', { name: 'Profile', exact: true }).click()
  await expect(settingsNavigation.getByRole('tab', { name: 'Profile', exact: true })).toHaveClass(
    /bg-brand-50/,
  )
  await expect(settingsNavigation.getByRole('tab', { name: 'Workspace' })).not.toHaveClass(
    /bg-brand-50/,
  )
  await page.screenshot({ path: testInfo.outputPath('settings-desktop.png'), fullPage: true })

  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('banner')).toHaveCSS('left', '0px')
  await expect(page.getByRole('heading', { name: 'Settings', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible()
  const mobileNavigationBox = await settingsNavigation.boundingBox()
  const mobileProfileBox = await page.locator('#profile').boundingBox()
  expect(mobileNavigationBox).not.toBeNull()
  expect(mobileProfileBox).not.toBeNull()
  expect(mobileNavigationBox!.y + mobileNavigationBox!.height).toBeLessThan(mobileProfileBox!.y)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.screenshot({ path: testInfo.outputPath('settings-mobile.png'), fullPage: true })
})

test('uploads and removes private organization branding through public account scope', async ({
  page,
}) => {
  const accountId = '3dd5d4ea-e4a0-43ab-aad4-4e58621955ee'
  const organizationId = '6404891c-f514-4b71-a55a-9b2658e79326'
  let logoAvailable = false
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    'base64',
  )

  await page.route('**/api/v1/accounts', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: accountId,
            name: 'Branding Preview',
            realm: 'branding.example.test',
            timezone: 'Asia/Manila',
            enabled: true,
            organization: {
              id: organizationId,
              name: 'Preview Organization',
              branding: { logo_available: false, logo_updated_at: null },
            },
            organization_role: 'account_administrator',
            permissions: {
              can_manage_extensions: true,
              can_manage_devices: true,
              can_manage_voicemail: true,
              can_manage_call_routing: true,
              can_manage_media: true,
              can_sync_call_detail_records: true,
              can_view_services: true,
              can_manage_account_settings: true,
              can_onboard_descendants: false,
            },
          },
        ],
      }),
    })
  })
  await page.route(`**/api/v1/accounts/${accountId}/organization-logo`, async (route) => {
    const request = route.request()

    if (request.method() === 'POST') {
      const body = request.postDataBuffer()?.toString('utf8') ?? ''
      expect(body).toContain('name="logo"')
      expect(body).toContain('preview.png')
      expect(body).not.toContain('switch_account_id')
      logoAvailable = true
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            organization_id: organizationId,
            logo_available: true,
            logo_updated_at: '2026-09-01T08:00:00+08:00',
          },
        }),
      })
      return
    }

    if (request.method() === 'DELETE') {
      logoAvailable = false
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            organization_id: organizationId,
            logo_available: false,
            logo_updated_at: null,
          },
        }),
      })
      return
    }

    expect(request.method()).toBe('GET')
    expect(logoAvailable).toBe(true)
    await route.fulfill({
      status: 200,
      contentType: 'image/png',
      headers: { 'Cache-Control': 'private, no-store', 'X-Content-Type-Options': 'nosniff' },
      body: png,
    })
  })

  await page.goto('/settings')
  await page.getByRole('tab', { name: 'Branding' }).click()
  await expect(page.getByRole('heading', { name: 'Organization branding' })).toBeVisible()
  await expect(page.getByText('The default GridPBX mark is currently used.')).toBeVisible()

  await page.getByRole('button', { name: 'Upload logo' }).click()
  await expect(page.getByText('Choose a logo image.')).toBeVisible()

  const dropzone = page.getByTestId('file-dropzone')
  await expect(dropzone.getByText('Drag and drop your logo here')).toBeVisible()
  const dataTransfer = await page.evaluateHandle((bytes) => {
    const transfer = new DataTransfer()
    transfer.items.add(
      new File([new Uint8Array(bytes)], 'preview.png', {
        type: 'image/png',
      }),
    )
    return transfer
  }, Array.from(png))
  await dropzone.dispatchEvent('dragenter', { dataTransfer })
  await expect
    .poll(() => dropzone.evaluate((element) => element.classList.contains('border-brand-400')))
    .toBe(true)
  await dropzone.dispatchEvent('drop', { dataTransfer })
  await expect(dropzone.getByText('preview.png')).toBeVisible()
  await expect
    .poll(() => dropzone.evaluate((element) => element.classList.contains('border-brand-400')))
    .toBe(false)

  await page.getByRole('button', { name: 'Upload logo' }).click()

  await expect(page.getByRole('button', { name: 'Update logo' })).toBeVisible()
  await expect(page.getByText('Custom logo is active in the GridPBX sidebar.')).toBeVisible()
  const globalNotification = page.getByTestId('global-notification')
  await expect(globalNotification).toContainText('Upload successful')
  await expect(globalNotification).toContainText('The file was uploaded successfully.')
  await globalNotification.getByRole('button', { name: 'Dismiss notification' }).click()
  await expect(page.locator('aside img[alt="Organization logo"]')).toBeVisible()
  await expect(page.locator('body')).not.toContainText('organization-branding/')
  await expect(page.locator('body')).not.toContainText('switch_account_id')

  await page.getByRole('button', { name: 'Remove logo' }).click()
  await page.getByRole('button', { name: 'Confirm removal' }).click()

  await expect(page.getByText('The default GridPBX mark is currently used.')).toBeVisible()
  await expect(globalNotification).toContainText('Delete successful')
  await expect(globalNotification).toContainText('The record was deleted successfully.')
  await expect(page.getByRole('button', { name: 'Upload logo' })).toBeVisible()
  await expect(page.locator('aside img[alt="Organization logo"]')).toHaveCount(0)
})

test('round trips a disposable organization logo through the live API', async ({ page }) => {
  test.skip(
    !liveLogoAccountId || !liveLogoPath,
    'Set GRID_E2E_ORGANIZATION_LOGO_ACCOUNT_ID and GRID_E2E_ORGANIZATION_LOGO_PATH.',
  )

  const accountId = liveLogoAccountId!
  const accountsRequest = page.waitForResponse(
    (response) =>
      response.request().method() === 'GET' && response.url().endsWith('/api/v1/accounts'),
  )
  await page.goto('/settings')
  const accountsResponse = await accountsRequest
  expect(accountsResponse.ok()).toBe(true)
  const accounts = (await accountsResponse.json()) as {
    data: Array<{
      id: string
      name: string
      organization: { name: string; branding: { logo_available: boolean } }
      permissions: { can_manage_account_settings: boolean }
    }>
  }
  const account = accounts.data.find(({ id }) => id === accountId)
  expect(account).toBeDefined()
  expect(account!.permissions.can_manage_account_settings).toBe(true)
  expect(account!.organization.branding.logo_available).toBe(false)
  const accountOptionName = `${account!.name} ${account!.organization.name}`

  let uploaded = false

  try {
    await page.getByRole('tab', { name: 'Workspace' }).click()
    await page.getByRole('button', { name: 'Settings workspace account' }).click()
    await page.getByRole('option', { name: accountOptionName, exact: true }).click()

    await page.getByRole('tab', { name: 'Branding' }).click()
    await page.getByLabel('Logo image').setInputFiles(liveLogoPath!)
    const uploadResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        response.url().endsWith(`/api/v1/accounts/${accountId}/organization-logo`),
    )
    await page.getByRole('button', { name: 'Upload logo' }).click()
    const uploadedResponse = await uploadResponse
    expect(uploadedResponse.ok()).toBe(true)
    const uploadedBody = await uploadedResponse.json()
    expect(uploadedBody).toMatchObject({
      data: { logo_available: true },
    })
    expect(JSON.stringify(uploadedBody)).not.toContain('logo_path')
    uploaded = true

    await expect(page.getByText('Custom logo is active in the GridPBX sidebar.')).toBeVisible()
    await expect(page.locator('aside img[alt="Organization logo"]')).toBeVisible()

    await page.getByRole('button', { name: 'Remove logo' }).click()
    const removeResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'DELETE' &&
        response.url().endsWith(`/api/v1/accounts/${accountId}/organization-logo`),
    )
    await page.getByRole('button', { name: 'Confirm removal' }).click()
    expect((await removeResponse).ok()).toBe(true)
    uploaded = false

    await expect(page.getByText('The default GridPBX mark is currently used.')).toBeVisible()
    await expect(page.locator('aside img[alt="Organization logo"]')).toHaveCount(0)
  } finally {
    if (uploaded) {
      await page.goto('/settings')
      await page.getByRole('tab', { name: 'Workspace' }).click()
      await page.getByRole('button', { name: 'Settings workspace account' }).click()
      await page.getByRole('option', { name: accountOptionName, exact: true }).click()
      await page.getByRole('tab', { name: 'Branding' }).click()
      await page.getByRole('button', { name: 'Remove logo' }).click()
      await page.getByRole('button', { name: 'Confirm removal' }).click()
      await expect(page.getByText('The default GridPBX mark is currently used.')).toBeVisible()
    }
  }
})
