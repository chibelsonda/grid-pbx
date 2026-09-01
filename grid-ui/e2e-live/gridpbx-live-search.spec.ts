import { expect, test } from '@playwright/test'

test.use({ viewport: { width: 1440, height: 800 } })

test('filters account choices immediately and debounces projected list searches', async ({
  page,
}) => {
  await page.goto('/')

  await page.getByRole('button', { name: /^Current account:/ }).click()
  const accountSearch = page.getByRole('searchbox', { name: 'Search accounts' })
  await accountSearch.fill('Roanna')

  const accountOptions = page.locator('[data-account-option]:not(:disabled)')
  await expect(accountOptions).toHaveCount(1)
  await expect(accountOptions.first()).toHaveAttribute('aria-label', 'Switch to Roanna Leonard')
  await page.keyboard.press('Escape')

  await page.goto('/devices')
  await expect(page.getByText('Loading projected devices…')).toHaveCount(0)

  const query = `live-search-${Date.now()}`
  const response = page.waitForResponse((candidate) => {
    const url = new URL(candidate.url())

    return (
      candidate.request().method() === 'GET' &&
      /\/api\/v1\/accounts\/[^/]+\/devices$/.test(url.pathname) &&
      url.searchParams.get('search') === query
    )
  })

  await page.getByRole('searchbox', { name: 'Search devices' }).fill(query)
  expect((await response).ok()).toBe(true)
  await expect(page.getByRole('searchbox', { name: 'Search devices' })).toHaveValue(query)
})
