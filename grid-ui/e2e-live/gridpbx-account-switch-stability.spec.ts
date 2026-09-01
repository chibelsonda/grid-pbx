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

test.use({ viewport: { width: 1440, height: 720 } })

test('keeps the current page and scroll position while switching accounts', async ({
  page,
}, testInfo) => {
  const issues = collectPageIssues(page)

  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await expect(page.getByText('Loading the operational overview…')).toHaveCount(0)

  await page.evaluate(() => window.scrollTo({ top: Math.min(900, document.body.scrollHeight) }))
  const initialScroll = await page.evaluate(() => window.scrollY)
  expect(initialScroll).toBeGreaterThan(200)
  const initialPath = new URL(page.url()).pathname
  const callQuality = page.getByRole('heading', { name: 'Call quality indicators' })
  const initialCallQualityBounds = await callQuality.boundingBox()
  expect(initialCallQualityBounds).not.toBeNull()

  await page.route(/\/api\/v1\/accounts\/[^/]+\/dashboard(?:\/[^?]+)?(?:\?.*)?$/, async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 300))
    await route.continue()
  })

  const accountButton = page.getByRole('button', { name: /^Current account:/ })
  const currentAccount = (await accountButton.getAttribute('aria-label'))
    ?.replace(/^Current account: /, '')
    .replace(/\. Open account search$/, '')
  expect(currentAccount).toBeTruthy()

  await accountButton.click()
  const menuOpenScroll = await page.evaluate(() => window.scrollY)
  const options = page.locator('[data-account-option]:not(:disabled)')
  const optionCount = await options.count()
  expect(optionCount).toBeGreaterThan(1)
  let target = options.first()
  let targetAccount = ''
  for (let index = 0; index < optionCount; index += 1) {
    const option = options.nth(index)
    const label = await option.getAttribute('aria-label')
    if (label !== `Switch to ${currentAccount}`) {
      target = option
      targetAccount = label?.replace(/^Switch to /, '') ?? ''
      break
    }
  }
  expect(targetAccount).toBeTruthy()

  const dashboardResponses = [
    /\/dashboard$/,
    /\/dashboard\/call-activity$/,
    /\/dashboard\/call-geography$/,
    /\/dashboard\/call-quality$/,
    /\/dashboard\/recent-missed-calls$/,
    /\/dashboard\/top-destinations$/,
  ].map((pathPattern) =>
    page.waitForResponse((response) => {
      const path = new URL(response.url()).pathname

      return response.request().method() === 'GET' && pathPattern.test(path)
    }),
  )
  await target.click()
  const accountChangedAlert = page.getByTestId('global-notification')
  await expect(accountChangedAlert).toBeVisible()
  await expect(accountChangedAlert).toContainText('Account changed')
  await expect(accountChangedAlert).toContainText(`Now viewing ${targetAccount}.`)
  await expect(accountChangedAlert).not.toContainText('account-one')
  const duringRefreshScroll = await page.evaluate(() => window.scrollY)
  await expect(page.getByText('Loading the operational overview…')).toHaveCount(0)
  await expect(page.getByText('Loading recent missed calls…')).toHaveCount(0)
  await expect(page.getByText('Loading call insights…')).toHaveCount(0)
  const refreshingCallQualityBounds = await callQuality.boundingBox()
  expect(refreshingCallQualityBounds).not.toBeNull()
  expect(refreshingCallQualityBounds!.y).toBeCloseTo(initialCallQualityBounds!.y, 0)
  await Promise.all(dashboardResponses)
  await expect(page.getByText('Loading the operational overview…')).toHaveCount(0)
  await expect(page.getByRole('dialog', { name: 'Account search' })).toBeHidden()
  await expect(accountButton).toHaveAttribute(
    'aria-label',
    `Current account: ${targetAccount}. Open account search`,
  )
  const settledScroll = await page.evaluate(() => window.scrollY)

  expect(new URL(page.url()).pathname).toBe(initialPath)
  expect(menuOpenScroll).toBeCloseTo(initialScroll, 0)
  expect(duringRefreshScroll).toBeCloseTo(initialScroll, 0)
  expect(settledScroll).toBeCloseTo(initialScroll, 0)
  expect(issues).toEqual([])
  await page.screenshot({
    path: testInfo.outputPath('dashboard-account-switched-in-place.png'),
    fullPage: false,
  })
})
