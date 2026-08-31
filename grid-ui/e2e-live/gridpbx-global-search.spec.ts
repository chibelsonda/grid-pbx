import { expect, test } from '@playwright/test'

test('searches the selected account and supports keyboard navigation', async ({ page }) => {
  const browserErrors: string[] = []
  let searchedUrl = ''

  page.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(message.text())
  })
  page.on('pageerror', (error) => browserErrors.push(error.message))

  await page.route('**/api/v1/accounts/*/search?*', async (route) => {
    searchedUrl = route.request().url()
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          query: 'support',
          total: 1,
          groups: [
            {
              type: 'menu',
              label: 'Menus & IVR',
              results: [
                {
                  id: '3b2c33f1-59a9-4a40-bf22-d3a6a62de411',
                  type: 'menu',
                  title: 'Support IVR',
                  subtitle: 'IVR menu',
                  matched_field: 'name',
                },
              ],
            },
          ],
        },
      }),
    })
  })

  await page.goto('/')
  const trigger = page.getByRole('button', { name: 'Search this workspace' })
  await expect(trigger).toBeEnabled()
  await trigger.click()

  const dialog = page.getByRole('dialog', { name: 'Search this workspace' })
  const input = dialog.getByPlaceholder('Search people, devices, numbers, routes…')
  await expect(input).toBeVisible()
  await dialog.getByRole('button', { name: 'Filter search types' }).click()
  await dialog.getByRole('button', { name: 'Menus', exact: true }).click()
  await input.fill('support')
  await expect(dialog.getByText('Support IVR')).toBeVisible()
  expect(new URL(searchedUrl).pathname).toMatch(/\/api\/v1\/accounts\/[0-9a-f-]+\/search$/)
  expect(new URL(searchedUrl).searchParams.get('q')).toBe('support')
  expect([
    ...new URL(searchedUrl).searchParams.getAll('types'),
    ...new URL(searchedUrl).searchParams.getAll('types[]'),
  ]).toEqual(['menu'])

  await input.press('ArrowDown')
  await input.press('Enter')
  await expect(page).toHaveURL(/\/menus\?search=Support(?:\+|%20)IVR$/)
  await expect(page.getByLabel('Search menus')).toHaveValue('Support IVR')

  await page.keyboard.press('Control+K')
  await expect(page.getByPlaceholder('Search people, devices, numbers, routes…')).toBeVisible()
  await expect(page.getByText('Recent')).toBeVisible()
  await expect(page.getByText('Support IVR')).toBeVisible()
  expect(
    await page.evaluate(() =>
      Object.keys(window.localStorage).filter((key) =>
        key.startsWith('gridpbx:global-search-recent:'),
      ),
    ),
  ).toEqual([])
  expect(browserErrors).toEqual([])
})

test('opens an exact UUID-backed result in its resource detail panel', async ({ page }) => {
  const mediaId = '6077c5a8-c4d1-497a-a80f-ed1fe04be8cd'
  const mediaRecord = {
    id: mediaId,
    name: 'Search Result Hold Music',
    description: 'Opened from global search',
    language: 'en-us',
    media_source: 'upload',
    content_type: 'audio/mpeg',
    content_length: 2048,
    prompt_id: null,
    streamable: false,
    is_music_on_hold: false,
    dependencies: {
      music_on_hold: 0,
      voicemail_greetings: 0,
      callflows: 0,
      total: 0,
      can_delete: true,
    },
    last_synced_at: null,
    sync_status: 'healthy',
    created_at: null,
    updated_at: null,
  }

  await page.route('**/api/v1/accounts/*/search?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          query: 'hold music',
          total: 1,
          groups: [
            {
              type: 'media',
              label: 'Media & Music on Hold',
              results: [
                {
                  id: mediaId,
                  type: 'media',
                  title: mediaRecord.name,
                  subtitle: 'upload · en-us',
                  matched_field: 'name',
                },
              ],
            },
          ],
        },
      }),
    })
  })
  await page.route(`**/api/v1/accounts/*/media/${mediaId}`, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: mediaRecord }),
    })
  })
  await page.route('**/api/v1/accounts/*/media?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [mediaRecord],
        links: { prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: { status: 'healthy', last_successful_at: null, error_message: null },
        },
      }),
    })
  })

  await page.goto('/')
  await page.getByRole('button', { name: 'Search this workspace' }).click()
  const input = page.getByPlaceholder('Search people, devices, numbers, routes…')
  await input.fill('hold music')
  await expect(page.getByText('Search Result Hold Music')).toBeVisible()
  await input.press('Enter')

  await expect(page).toHaveURL(new RegExp(`/media\\?media=${mediaId}$`))
  await expect(
    page.getByRole('heading', { name: 'Search Result Hold Music' }).first(),
  ).toBeVisible()
  await expect(
    page.getByTestId('slide-over-content').getByText('Opened from global search'),
  ).toBeVisible()
})

test('searches a projected Callflow through the live account endpoint', async ({ page }) => {
  const accountsResponsePromise = page.waitForResponse(
    (response) => new URL(response.url()).pathname === '/api/v1/accounts',
  )
  await page.goto('/')
  await accountsResponsePromise
  const trigger = page.getByRole('button', { name: 'Search this workspace' })
  await expect(trigger).toBeEnabled()

  const accountId = await page.evaluate(() =>
    window.localStorage.getItem('gridpbx:selected-account'),
  )
  if (!accountId) throw new Error('The authenticated workspace has no selected account.')

  const callflowsResponsePromise = page.waitForResponse(
    (response) => new URL(response.url()).pathname === `/api/v1/accounts/${accountId}/callflows`,
  )
  await page.goto('/call-routing')
  const callflowsResponse = await callflowsResponsePromise
  expect(callflowsResponse.ok()).toBe(true)
  const callflowsPayload = (await callflowsResponse.json()) as {
    data: Array<{ id: string; name: string | null }>
  }
  const callflow = callflowsPayload.data.find(
    ({ name }) => typeof name === 'string' && name.trim().length >= 2 && name.trim().length <= 100,
  )
  if (!callflow?.name) throw new Error('The selected account has no searchable projected Callflow.')

  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Search this workspace' })
  const searchResponsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'GET' &&
      new URL(response.url()).pathname === `/api/v1/accounts/${accountId}/search`,
  )
  await dialog.getByPlaceholder('Search people, devices, numbers, routes…').fill(callflow.name)

  const searchResponse = await searchResponsePromise
  expect(searchResponse.ok()).toBe(true)
  const searchPayload = (await searchResponse.json()) as {
    data: {
      query: string
      groups: Array<{
        type: string
        results: Array<Record<string, unknown>>
      }>
    }
  }
  const result = searchPayload.data.groups
    .find(({ type }) => type === 'callflow')
    ?.results.find(({ id }) => id === callflow.id)

  expect(searchPayload.data.query).toBe(callflow.name)
  expect(result).toBeDefined()
  expect(Object.keys(result ?? {}).sort()).toEqual([
    'id',
    'matched_field',
    'subtitle',
    'title',
    'type',
  ])
  expect(result?.id).toMatch(
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
  )
  await expect(dialog.getByText(callflow.name, { exact: true }).first()).toBeVisible()
})
