import { expect, test, type Locator } from '@playwright/test'

interface ContentEdges {
  left: number
  right: number
}

async function contentEdges(container: Locator): Promise<ContentEdges> {
  return container.evaluate((element) => {
    const bounds = element.getBoundingClientRect()
    const styles = window.getComputedStyle(element)

    return {
      left: bounds.left + Number.parseFloat(styles.paddingLeft),
      right: bounds.right - Number.parseFloat(styles.paddingRight),
    }
  })
}

test.use({ viewport: { width: 2048, height: 900 } })

test('keeps the polished workspace header usable on desktop and mobile', async ({
  page,
}, testInfo) => {
  await page.goto('/call-routing')
  await expect(page.getByRole('heading', { name: 'Callflows', exact: true })).toBeVisible()

  const header = page.getByRole('banner')
  const userMenuButton = header.getByRole('button', { name: /^Open user menu for / })
  const workspaceSearch = header.getByRole('button', { name: 'Search this workspace' })
  await expect(workspaceSearch).toBeVisible()
  await expect(header.getByRole('button', { name: 'Current account' })).toBeVisible()
  await expect(userMenuButton).toBeVisible()
  await expect(userMenuButton.locator('.app-header-foreground')).toBeVisible()
  await expect(header.locator('[data-app-header-account-switcher]')).toBeVisible()
  expect(await header.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true)
  await header.screenshot({ path: testInfo.outputPath('header-desktop.png') })

  await userMenuButton.click()
  const desktopMenu = page.getByRole('menu')
  await expect(desktopMenu).toBeVisible()
  await expect(desktopMenu.getByText('Switch account', { exact: true })).toBeHidden()
  await page.keyboard.press('Escape')

  await page.setViewportSize({ width: 1024, height: 768 })
  await expect(header).toHaveCSS('left', '280px')
  await expect(userMenuButton.locator('.app-header-foreground')).toBeHidden()
  expect(await header.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true)

  await page.setViewportSize({ width: 390, height: 844 })
  await expect(header).toHaveCSS('left', '0px')
  await expect(header.getByRole('button', { name: /^Current account:/ })).toBeVisible()
  await expect(header.getByRole('button', { name: 'Search this workspace' })).toBeVisible()
  await header.getByRole('button', { name: /^Current account:/ }).click()
  await expect(page.getByRole('searchbox', { name: 'Search accounts' })).toBeVisible()
  await page.keyboard.press('Escape')
  await userMenuButton.click()

  const mobileMenu = page.getByRole('menu')
  await expect(mobileMenu.getByText('Switch account', { exact: true })).toHaveCount(0)
  await expect(mobileMenu).toHaveCSS('opacity', '1')
  await expect(mobileMenu).toHaveCSS('background-color', 'rgb(255, 255, 255)')
  expect(await header.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true)
  await page.evaluate(
    () =>
      new Promise<void>((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
      ),
  )
  await page.screenshot({
    path: testInfo.outputPath('header-mobile.png'),
    clip: { x: 0, y: 0, width: 390, height: 360 },
  })
})

test('opens implemented profile destinations from the user menu', async ({ page }) => {
  await page.goto('/')

  const userMenuButton = page
    .getByRole('banner')
    .getByRole('button', { name: /^Open user menu for / })
  await userMenuButton.click()

  const userMenu = page.getByRole('menu')
  await expect(userMenu.getByText('Current account', { exact: true })).toBeVisible()
  await expect(userMenu.getByText('Profile & settings', { exact: true })).toBeVisible()
  await expect(userMenu.getByText('Access & security', { exact: true })).toBeVisible()
  await userMenu.getByRole('menuitem', { name: /Profile & settings/ }).click()

  await expect(page).toHaveURL(/\/settings#profile$/)
  await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible()

  await userMenuButton.click()
  const accessMenu = page.getByRole('menu')
  await expect(accessMenu).toHaveCSS('opacity', '1')
  await accessMenu.getByRole('menuitem', { name: /Access & security/ }).click()
  await expect(page).toHaveURL(/\/settings#access-security$/)
  await expect(page.getByRole('heading', { name: 'Access and security' })).toBeVisible()
})

test('keeps projection freshness with page actions instead of a standalone row', async ({
  page,
}) => {
  const pages = [
    { path: '/call-history', heading: 'Call History', sync: true },
    { path: '/phone-numbers', heading: 'Phone Numbers', sync: true },
    { path: '/call-routing', heading: 'Callflows', sync: true },
    { path: '/media', heading: 'Media & Music on Hold', sync: true },
    { path: '/business-hours', heading: 'Business Hours & Schedules', sync: true },
    { path: '/extensions', heading: 'People & Extensions', sync: true },
    { path: '/queues', heading: 'Queues & Agents', sync: true },
    { path: '/billing', heading: 'Billing', sync: false },
  ]

  for (const item of pages) {
    await page.goto(item.path)
    await expect(page.getByRole('heading', { name: item.heading, exact: true })).toBeVisible()

    const freshness = page.getByTestId('projection-freshness')
    const pageHeader = page.locator('section').filter({ has: freshness }).first()
    await expect(freshness).toBeVisible()
    await expect(pageHeader).toContainText(item.heading)
    await expect(freshness).toContainText(
      /^(Last synchronized|Not synchronized yet|Last synchronization failed)/,
    )

    if (item.sync) {
      await expect(pageHeader.getByRole('button', { name: 'Sync from Switch' })).toBeVisible()
      await expect(pageHeader.getByRole('button', { name: 'Sync', exact: true })).toHaveCount(0)
    }

    const [freshnessBox, headerBox] = await Promise.all([
      freshness.boundingBox(),
      pageHeader.boundingBox(),
    ])
    expect(freshnessBox).not.toBeNull()
    expect(headerBox).not.toBeNull()
    expect(freshnessBox!.y).toBeGreaterThanOrEqual(headerBox!.y)
    expect(freshnessBox!.y + freshnessBox!.height).toBeLessThanOrEqual(
      headerBox!.y + headerBox!.height + 1,
    )
  }
})

test('keeps header controls legible with a dark theme', async ({ page }, testInfo) => {
  await page.addInitScript(() => {
    window.localStorage.setItem(
      'gridpbx.shell-theme.v1',
      JSON.stringify({ header: 'midnight', sidebar: 'light' }),
    )
  })
  await page.goto('/settings')

  const header = page.getByRole('banner')
  const workspaceSearch = header.getByRole('button', { name: 'Search this workspace' })
  const accountButton = header.getByRole('button', { name: /^Current account:/ })
  await expect(header).toHaveAttribute('data-theme', 'midnight')
  await expect(workspaceSearch).toHaveCSS('color', 'rgb(203, 213, 225)')
  await expect(workspaceSearch).toHaveCSS('background-color', 'rgba(255, 255, 255, 0.08)')

  await accountButton.click()
  await expect(accountButton).toHaveCSS('color', 'rgb(255, 255, 255)')
  await expect(accountButton.locator('.app-header-muted').first()).toHaveCSS(
    'color',
    'rgb(255, 255, 255)',
  )
  await page.screenshot({
    path: testInfo.outputPath('header-midnight-account-search.png'),
    clip: { x: 0, y: 0, width: 2048, height: 420 },
  })
})

test('searches only authorized projected accounts from the main header', async ({
  page,
}, testInfo) => {
  const firstAccountId = '4cf73387-8a94-456b-aaf2-c48a2882cb61'
  const secondAccountId = 'da2a2904-93d7-4231-a8bf-465fbb2eb451'

  await page.route('**/api/v1/accounts', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: firstAccountId,
            name: 'GridPBX',
            realm: 'gridpbx.example.test',
            timezone: 'Asia/Manila',
            enabled: true,
            organization: {
              id: '5ee0ed47-8f60-4930-9244-bc21b0ac69bf',
              name: 'Grid Organization',
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
          {
            id: secondAccountId,
            name: 'Roanna Leonard',
            realm: 'roanna.example.test',
            timezone: 'Asia/Manila',
            enabled: true,
            organization: {
              id: 'd914fe92-725e-478f-987b-e7cb65274401',
              name: 'Leonard Group',
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

  await page.goto('/settings')
  await page.getByRole('button', { name: /^Current account: GridPBX/ }).click()
  const accountSearch = page.getByRole('dialog', { name: 'Account search' })
  const search = accountSearch.getByRole('searchbox', { name: 'Search accounts' })
  await expect(accountSearch).toHaveCSS('opacity', '1')
  await expect(search).toBeFocused()
  await search.fill('roanna.example')

  await expect(page.getByRole('button', { name: 'Switch to GridPBX' })).toHaveCount(0)
  await accountSearch.evaluate(async (element) => {
    await Promise.all(
      element.getAnimations({ subtree: true }).map((animation) => animation.finished),
    )
  })
  await page.screenshot({ path: testInfo.outputPath('header-account-search.png') })
  await page.getByRole('button', { name: 'Switch to Roanna Leonard' }).click()

  await expect(page.getByRole('button', { name: /^Current account: Roanna Leonard/ })).toBeVisible()
  expect(await page.evaluate(() => localStorage.getItem('gridpbx:selected-account'))).toBe(
    secondAccountId,
  )
  await expect(page.locator('body')).not.toContainText('switch_account_id')
  await expect(page.locator('body')).not.toContainText('raw-switch-account')
})

for (const route of ['/extensions', '/devices', '/accounts']) {
  test(`aligns the ${route} header and main content gutters`, async ({ page }) => {
    await page.goto(route)

    const headerContainer = page.locator('main > section .page-container').first()
    const bodyContainer = page.locator('main > div.page-container').first()

    await expect(headerContainer).toBeVisible()
    await expect(bodyContainer).toBeVisible()

    const header = await contentEdges(headerContainer)
    const body = await contentEdges(bodyContainer)

    expect(Math.abs(header.left - body.left)).toBeLessThan(1)
    expect(Math.abs(header.right - body.right)).toBeLessThan(1)

    if (route === '/extensions') {
      await page.screenshot({ path: 'test-results/live/layout-extensions.png', fullPage: true })
    }
  })
}
