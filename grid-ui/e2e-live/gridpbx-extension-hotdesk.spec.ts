import { expect, test, type Locator, type Page } from '@playwright/test'

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

type DisposableExtension = {
  deleteUrl: string
  number: string
}

type LiveExtensionConfiguration = {
  credentials: { password_configured: boolean }
  caller_id: { internal: { name: string | null; number: string | null } }
  call_forward: {
    enabled: boolean
    number: string | null
    keep_caller_id: boolean
    require_keypress: boolean
  }
  call_restriction: Record<string, { action: 'deny' | 'inherit' }>
  call_recording: {
    outbound: { offnet: { enabled: boolean; format: 'mp3' | 'wav' } }
  }
  hotdesk: {
    enabled: boolean
    id: string | null
    require_pin: boolean
    pin_configured: boolean
  }
}

type LiveExtensionDetail = {
  id: string
  username: string | null
  configuration: LiveExtensionConfiguration
}

type LiveVoicemailBox = {
  id: string
  name: string | null
  mailbox: string | null
  timezone: string | null
  notification_emails: string[]
  transcribe: boolean
  require_pin: boolean
  pin_configured: boolean
  configuration: {
    check_if_owner: boolean
    delete_after_notify: boolean
    include_message_on_notify: boolean
    include_transcription_on_notify: boolean
    media_extension: 'mp3' | 'mp4' | 'wav'
    not_configurable: boolean
    oldest_message_first: boolean
    save_after_notify: boolean
    skip_envelope: boolean
    skip_greeting: boolean
    skip_instructions: boolean
    is_voicemail_ff_rw_enabled: boolean
    seek_duration_ms: number
    notify_callback: {
      disabled: boolean
      number: string | null
      attempts: number | null
      interval_s: number | null
      timeout_s: number | null
      schedule: number[]
    } | null
  }
}

async function authenticatedJson<T>(
  page: Page,
  url: string,
  init: { method?: string; body?: unknown } = {},
): Promise<{ status: number; data: T }> {
  return page.evaluate(
    async ({ requestUrl, method, body }) => {
      const token = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
          ?.split('=')[1] ?? '',
      )
      const response = await fetch(requestUrl, {
        method,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': token,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      })

      return { status: response.status, data: (await response.json()) as T }
    },
    { requestUrl: url, method: init.method ?? 'GET', body: init.body },
  )
}

async function synchronizeExtensions(
  page: Page,
  apiOrigin: string,
  accountId: string,
): Promise<void> {
  const started = await authenticatedJson<{ data: { id: string; status: string } }>(
    page,
    `${apiOrigin}/api/v1/accounts/${accountId}/sync/extensions`,
    { method: 'POST' },
  )
  if (started.status !== 202) {
    throw new Error(`Extension synchronization could not start: ${JSON.stringify(started.data)}`)
  }

  await expect
    .poll(
      async () => {
        const run = await authenticatedJson<{
          data: { status: string; error_message: string | null }
        }>(
          page,
          `${apiOrigin}/api/v1/accounts/${accountId}/sync/extensions/${started.data.data.id}`,
        )
        if (run.data.data.status === 'failed') {
          throw new Error(run.data.data.error_message ?? 'Extension synchronization failed.')
        }

        return run.data.data.status
      },
      { timeout: 30_000 },
    )
    .toBe('succeeded')
}

async function extensionDetail(page: Page, detailUrl: string): Promise<LiveExtensionDetail> {
  const response = await authenticatedJson<{ data: LiveExtensionDetail }>(page, detailUrl)
  if (response.status !== 200) {
    throw new Error(`Extension detail request failed: ${JSON.stringify(response.data)}`)
  }

  return response.data.data
}

async function voicemailDetail(page: Page, detailUrl: string): Promise<LiveVoicemailBox> {
  const response = await authenticatedJson<{ data: LiveVoicemailBox }>(page, detailUrl)
  if (response.status !== 200) {
    throw new Error(`Voicemail detail request failed: ${JSON.stringify(response.data)}`)
  }

  return response.data.data
}

async function deleteDisposableExtension(
  page: Page,
  extension: DisposableExtension,
): Promise<void> {
  const cleanup = await page.evaluate(async ({ deleteUrl, number }) => {
    const token = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    )
    const response = await fetch(deleteUrl, {
      method: 'DELETE',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify({ confirmation: number }),
    })

    return { status: response.status, body: await response.text() }
  }, extension)

  if (cleanup.status !== 204) {
    throw new Error(`Disposable Extension cleanup failed: ${cleanup.body}`)
  }
}

async function selectVoicemailSection(scope: Page | Locator, section: 'Basic' | 'Options') {
  const views = scope.getByRole('tablist', { name: 'Form sections' })
  if (section === 'Basic') {
    await views.getByRole('tab', { name: 'Basic' }).click()

    return
  }

  await views.getByRole('tab', { name: 'Advanced' }).click()
  await scope
    .getByRole('tablist', { name: 'Voicemail advanced sections' })
    .getByRole('tab', { name: 'Options' })
    .click()
}

test('shows and validates login credentials and hotdesk in the Extension slide-over', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/extensions')
  await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
  await page.getByRole('button', { name: 'Create extension' }).click()

  await page.getByRole('button', { name: 'Timezone' }).click()
  await expect(page.getByRole('option', { name: /Account default/ })).toBeVisible()
  await page.getByRole('option', { name: /Account default/ }).click()

  const starterDevice = page.locator('article').filter({ hasText: 'Initial device' })
  await starterDevice.getByRole('switch', { name: 'Create' }).click()
  if ((await page.getByRole('heading', { name: 'Configure device' }).count()) === 0) {
    await starterDevice.getByRole('button', { name: /Configure the initial device/ }).click()
  }
  await expect(page.getByRole('heading', { name: 'Configure device' })).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(1)
  const deviceDrawer = page.getByRole('dialog', { name: 'Configure device' })
  for (const type of [
    'VoIP phone',
    'Cell phone',
    'Smartphone',
    'Landline',
    'Softphone',
    'Fax',
    'ATA',
    'SIP URI',
  ]) {
    await expect(deviceDrawer.getByRole('button', { name: type })).toBeVisible()
  }
  await deviceDrawer.getByRole('tab', { name: 'Advanced' }).click()
  await expect(deviceDrawer.getByRole('tab', { name: 'Caller ID' })).toBeVisible()
  await expect(deviceDrawer.getByRole('tab', { name: 'Restrictions' })).toBeVisible()
  await deviceDrawer.getByRole('tab', { name: 'Basic', exact: true }).first().click()
  await deviceDrawer.getByPlaceholder('Reception Desk Phone').fill('Initial desk phone')
  await deviceDrawer.getByRole('button', { name: 'Use this device' }).click()
  await expect(page.getByRole('heading', { name: 'Configure device' })).toHaveCount(0)
  await expect(starterDevice.getByText('Initial desk phone', { exact: true })).toBeVisible()

  const credentials = page.locator('article').filter({ hasText: 'Switch portal login' })
  const username = credentials.getByRole('textbox', { name: 'Login username' })
  await username.fill('alice.operator')

  const extensionTabs = page.getByRole('tablist', { name: 'Extension form sections' })
  await extensionTabs.getByRole('tab', { name: 'Advanced' }).click()
  const advancedTabs = page.getByRole('tablist', { name: 'Extension advanced sections' })
  for (const tab of [
    'Caller ID',
    'Options',
    'Call Forward',
    'Password Management',
    'Hot Desking',
    'Restrictions',
    'Recording',
    'Media',
    'Routing & Profile',
    'Metaflows',
  ]) {
    await expect(advancedTabs.getByRole('tab', { name: tab, exact: true })).toBeVisible()
  }

  await advancedTabs.getByRole('tab', { name: 'Password Management', exact: true }).click()
  const passwordManagement = page.locator('article').filter({ hasText: 'Password management' })
  const password = passwordManagement.getByLabel('Password', { exact: true })
  const confirmation = passwordManagement.getByLabel('Confirm password')
  await password.fill('short')
  await confirmation.fill('different-password')

  await advancedTabs.getByRole('tab', { name: 'Hot Desking' }).click()
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

  const firstName = page.getByLabel('First name')
  await expect(firstName).toHaveAttribute('aria-invalid', 'true')
  await expect(firstName).toHaveClass(/border-red-400/)
  await extensionTabs.getByRole('tab', { name: 'Advanced' }).click()
  await expect(hotdesk.getByText('Use 4–15 dial-pad characters.')).toBeVisible()
  await expect(hotdeskId).toHaveClass(/border-red-400/)
  await expect(
    hotdesk.getByText('Enter a hotdesk PIN when PIN protection is enabled.'),
  ).toBeVisible()
  await advancedTabs.getByRole('tab', { name: 'Password Management', exact: true }).click()
  await expect(passwordManagement.getByText('Use at least 6 characters.')).toBeVisible()
  await expect(passwordManagement.getByText('Passwords do not match.')).toBeVisible()
  await expect(passwordManagement.locator('input[type="password"]').first()).toHaveClass(
    /border-red-400/,
  )
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('persists, edits, synchronizes, and clears Extension advanced fields in Switch', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const number = Date.now().toString().slice(-8)
  const username = `live.${number}`
  const password = `GridPBX-${number}!`
  const originalForwardingNumber = `+1555${number}`
  const updatedForwardingNumber = `+1666${number}`
  let created: DisposableExtension | null = null

  try {
    await page.goto('/extensions')
    await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
    await page.getByRole('button', { name: 'Create extension' }).click()
    await page.getByLabel('First name').fill('GridPBX')
    await page.getByLabel('Last name').fill('Advanced Live')
    await page.getByLabel('Extension number').fill(number)
    await page.getByLabel('Login username').fill(username)

    const voicemail = page.locator('article').filter({ hasText: 'Voicemail fallback' })
    await voicemail.getByRole('switch', { name: 'Create' }).click()

    const outerTabs = page.getByRole('tablist', { name: 'Extension form sections' })
    await outerTabs.getByRole('tab', { name: 'Advanced' }).click()
    const advancedTabs = page.getByRole('tablist', { name: 'Extension advanced sections' })

    await advancedTabs.getByRole('tab', { name: 'Caller ID' }).click()
    await page.getByLabel('Internal caller-ID name').fill('GridPBX Live Create')
    await page.getByLabel('Internal caller-ID number').fill(number)

    await advancedTabs.getByRole('tab', { name: 'Call Forward' }).click()
    const forwarding = page.getByRole('switch', { name: 'Enable call forwarding' })
    await forwarding.click()
    await page.getByLabel('Forwarding destination').fill(originalForwardingNumber)
    await page.getByRole('button', { name: 'Forwarding behavior' }).click()
    const requireKeypress = page.getByRole('switch', { name: 'Require keypress' })
    await expect(requireKeypress).toBeChecked()

    await advancedTabs.getByRole('tab', { name: 'Password Management', exact: true }).click()
    await page.getByLabel('Password', { exact: true }).fill(password)
    await page.getByLabel('Confirm password').fill(password)

    await advancedTabs.getByRole('tab', { name: 'Recording' }).click()
    const recording = page.locator('article').filter({ hasText: 'User call recording' })
    const outbound = recording.locator('section').filter({ hasText: /^Outbound/ })
    await outbound.getByRole('switch', { name: 'Off-net' }).click()

    await advancedTabs.getByRole('tab', { name: 'Hot Desking' }).click()
    const hotdesk = page.locator('article').filter({ hasText: 'Hotdesk profile' })
    await hotdesk.getByRole('switch', { name: 'Enabled' }).click()
    await hotdesk.getByRole('textbox', { name: 'Hotdesk ID' }).fill(number)
    await hotdesk.getByRole('switch', { name: 'Require a PIN' }).click()
    await hotdesk.getByLabel('Hotdesk PIN').fill(number.slice(-6))

    await advancedTabs.getByRole('tab', { name: 'Restrictions' }).click()
    const restrictions = page.locator('article').filter({ hasText: 'Call restrictions' })
    const international = restrictions
      .getByText('International', { exact: true })
      .locator('..')
      .locator('..')
    await international.getByRole('button').click()
    await page.getByRole('option', { name: 'Deny', exact: true }).click()

    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Create extension', exact: true }).last().click()
    const response = await createResponse
    if (response.status() !== 201) {
      throw new Error(`Disposable advanced Extension creation failed: ${await response.text()}`)
    }

    const responseUrl = new URL(response.url())
    const submittedCreate = response.request().postDataJSON() as {
      call_forward: { require_keypress: boolean }
    }
    expect(submittedCreate.call_forward.require_keypress).toBe(true)
    const accountId = responseUrl.pathname.match(/\/accounts\/([^/]+)\//)?.[1]
    const result = (await response.json()) as { data: LiveExtensionDetail }
    if (!accountId)
      throw new Error('Unable to derive the account identifier from the API response.')
    const detailUrl = `${responseUrl.origin}${responseUrl.pathname}/${result.data.id}`
    created = { deleteUrl: detailUrl, number }

    expect(result.data.username).toBe(username)
    expect(result.data.configuration.credentials.password_configured).toBe(true)
    expect(result.data.configuration.caller_id.internal).toEqual({
      name: 'GridPBX Live Create',
      number,
    })
    expect(result.data.configuration.call_forward).toMatchObject({
      enabled: true,
      number: originalForwardingNumber,
      require_keypress: true,
    })
    expect(result.data.configuration.call_restriction.international?.action).toBe('deny')
    expect(result.data.configuration.call_recording.outbound.offnet.enabled).toBe(true)
    expect(result.data.configuration.hotdesk).toMatchObject({
      enabled: true,
      id: number,
      require_pin: true,
      pin_configured: true,
    })

    await synchronizeExtensions(page, responseUrl.origin, accountId)
    const synchronizedCreate = await extensionDetail(page, detailUrl)
    expect(synchronizedCreate.configuration.caller_id.internal.name).toBe('GridPBX Live Create')
    expect(synchronizedCreate.configuration.call_forward.number).toBe(originalForwardingNumber)
    expect(synchronizedCreate.configuration.call_recording.outbound.offnet.enabled).toBe(true)
    expect(synchronizedCreate.configuration.hotdesk.pin_configured).toBe(true)

    await page.goto(`/extensions/${result.data.id}`)
    await page.getByRole('button', { name: 'Edit' }).click()
    await page
      .getByRole('tablist', { name: 'Extension form sections' })
      .getByRole('tab', { name: 'Advanced' })
      .click()
    const editTabs = page.getByRole('tablist', { name: 'Extension advanced sections' })
    await editTabs.getByRole('tab', { name: 'Caller ID' }).click()
    await page.getByLabel('Internal caller-ID name').fill('GridPBX Live Edit')
    await editTabs.getByRole('tab', { name: 'Call Forward' }).click()
    await page.getByLabel('Forwarding destination').fill(updatedForwardingNumber)
    await editTabs.getByRole('tab', { name: 'Hot Desking' }).click()
    await page.getByRole('textbox', { name: 'Hotdesk ID' }).fill(`${number.slice(0, -1)}7`)

    const updateResponse = page.waitForResponse(
      (candidate) => candidate.request().method() === 'PUT' && candidate.url() === detailUrl,
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    expect((await updateResponse).status()).toBe(200)
    await synchronizeExtensions(page, responseUrl.origin, accountId)
    const synchronizedEdit = await extensionDetail(page, detailUrl)
    expect(synchronizedEdit.configuration.caller_id.internal.name).toBe('GridPBX Live Edit')
    expect(synchronizedEdit.configuration.call_forward.number).toBe(updatedForwardingNumber)
    expect(synchronizedEdit.configuration.hotdesk.id).toBe(`${number.slice(0, -1)}7`)

    await page.reload()
    await page.getByRole('button', { name: 'Edit' }).click()
    const login = page.locator('article').filter({ hasText: 'Switch portal login' })
    await login.getByRole('button', { name: 'Remove login credentials' }).click()
    await page
      .getByRole('tablist', { name: 'Extension form sections' })
      .getByRole('tab', { name: 'Advanced' })
      .click()
    const clearTabs = page.getByRole('tablist', { name: 'Extension advanced sections' })

    await clearTabs.getByRole('tab', { name: 'Caller ID' }).click()
    await page.getByLabel('Internal caller-ID name').fill('')
    await page.getByLabel('Internal caller-ID number').fill('')

    await clearTabs.getByRole('tab', { name: 'Call Forward' }).click()
    await page.getByLabel('Forwarding destination').fill('')
    await page.getByRole('switch', { name: 'Enable call forwarding' }).click()

    await clearTabs.getByRole('tab', { name: 'Recording' }).click()
    await page
      .locator('article')
      .filter({ hasText: 'User call recording' })
      .locator('section')
      .filter({ hasText: /^Outbound/ })
      .getByRole('switch', { name: 'Off-net' })
      .click()

    await clearTabs.getByRole('tab', { name: 'Hot Desking' }).click()
    const clearHotdesk = page.locator('article').filter({ hasText: 'Hotdesk profile' })
    await clearHotdesk.getByRole('switch', { name: 'Require a PIN' }).click()
    await clearHotdesk.getByRole('button', { name: 'Remove configured PIN' }).click()
    await clearHotdesk.getByRole('switch', { name: 'Enabled' }).click()

    await clearTabs.getByRole('tab', { name: 'Restrictions' }).click()
    const clearRestrictions = page.locator('article').filter({ hasText: 'Call restrictions' })
    const clearInternational = clearRestrictions
      .getByText('International', { exact: true })
      .locator('..')
      .locator('..')
    await clearInternational.getByRole('button').click()
    await page.getByRole('option', { name: 'Inherit account policy', exact: true }).click()

    const clearResponse = page.waitForResponse(
      (candidate) => candidate.request().method() === 'PUT' && candidate.url() === detailUrl,
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    expect((await clearResponse).status()).toBe(200)
    await synchronizeExtensions(page, responseUrl.origin, accountId)
    const synchronizedClear = await extensionDetail(page, detailUrl)
    expect(synchronizedClear.username).toBeNull()
    expect(synchronizedClear.configuration.credentials.password_configured).toBe(false)
    expect(synchronizedClear.configuration.caller_id.internal).toEqual({ name: null, number: null })
    expect(synchronizedClear.configuration.call_forward.enabled).toBe(false)
    expect(synchronizedClear.configuration.call_forward.number).toBeNull()
    expect(
      synchronizedClear.configuration.call_restriction.international?.action ?? 'inherit',
    ).toBe('inherit')
    expect(synchronizedClear.configuration.call_recording.outbound.offnet.enabled).toBe(false)
    expect(synchronizedClear.configuration.hotdesk).toMatchObject({
      enabled: false,
      require_pin: false,
      pin_configured: false,
    })
    expect(issues).toEqual([])
  } finally {
    if (created) await deleteDisposableExtension(page, created)
  }
})

test('creates an Extension with a Device, clears a Device option, and removes the aggregate', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const number = Date.now().toString().slice(-8)
  const deviceName = `GridPBX aggregate device ${number}`
  let created: DisposableExtension | null = null
  let deviceId: string | null = null

  try {
    await page.goto('/extensions')
    await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
    await page.getByRole('button', { name: 'Create extension' }).click()
    await page.getByLabel('First name').fill('GridPBX')
    await page.getByLabel('Last name').fill('Device Aggregate')
    await page.getByLabel('Extension number').fill(number)

    const voicemail = page.locator('article').filter({ hasText: 'Voicemail fallback' })
    await voicemail.getByRole('switch', { name: 'Create' }).click()

    const initialDevice = page.locator('article').filter({ hasText: 'Initial device' })
    await initialDevice.getByRole('switch', { name: 'Create' }).click()
    const deviceDrawer = page.getByRole('dialog', { name: 'Configure device' })
    await expect(page.getByRole('heading', { name: 'Configure device' })).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(1)
    await expect
      .poll(() => page.getByTestId('slide-over-content').evaluate((element) => element.scrollTop))
      .toBe(0)
    await expect(deviceDrawer.getByRole('button', { name: 'VoIP phone' })).toBeVisible()
    await deviceDrawer.getByPlaceholder('Reception Desk Phone').fill(deviceName)
    await deviceDrawer.getByRole('tab', { name: 'Advanced' }).click()
    await deviceDrawer.getByRole('tab', { name: 'Options' }).click()
    await deviceDrawer.getByRole('switch', { name: 'Hide from contact list' }).click()
    await deviceDrawer.getByRole('button', { name: 'Use this device' }).click()
    await expect(initialDevice.getByText(deviceName, { exact: true })).toBeVisible()

    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Create extension', exact: true }).last().click()
    const response = await createResponse
    if (response.status() !== 201) {
      throw new Error(`Disposable Extension + Device creation failed: ${await response.text()}`)
    }

    const responseUrl = new URL(response.url())
    const result = (await response.json()) as {
      data: { id: string; devices: Array<{ id: string; name: string | null }> }
    }
    deviceId = result.data.devices.find((device) => device.name === deviceName)?.id ?? null
    created = {
      deleteUrl: `${responseUrl.origin}${responseUrl.pathname}/${result.data.id}`,
      number,
    }

    expect(deviceId).not.toBeNull()
    await expect(
      page.getByRole('heading', { level: 1, name: 'GridPBX Device Aggregate' }),
    ).toBeVisible()
    const assignedDevices = page.locator('article').filter({ hasText: 'Assigned devices' })
    await expect(assignedDevices.getByText(deviceName, { exact: true })).toBeVisible()

    await page.goto(`/devices/${deviceId}/edit`)
    await expect(page.getByRole('heading', { name: 'Edit device' })).toBeVisible()
    const editDrawer = page.getByRole('dialog', { name: 'Edit device' })
    await editDrawer.getByRole('tab', { name: 'Advanced' }).click()
    await editDrawer.getByRole('tab', { name: 'Options' }).click()
    const hideFromContacts = editDrawer.getByRole('switch', { name: 'Hide from contact list' })
    await expect(hideFromContacts).toBeChecked()
    await hideFromContacts.click()

    const updateResponse = page.waitForResponse(
      (update) =>
        update.request().method() === 'PUT' &&
        new URL(update.url()).pathname.endsWith(`/devices/${deviceId}`),
    )
    await editDrawer.getByRole('button', { name: 'Save changes' }).click()
    expect((await updateResponse).status()).toBe(200)

    await page.goto(`/devices/${deviceId}/edit`)
    const verifyDrawer = page.getByRole('dialog', { name: 'Edit device' })
    await verifyDrawer.getByRole('tab', { name: 'Advanced' }).click()
    await verifyDrawer.getByRole('tab', { name: 'Options' }).click()
    await expect(
      verifyDrawer.getByRole('switch', { name: 'Hide from contact list' }),
    ).not.toBeChecked()
    expect(issues).toEqual([])
  } finally {
    if (created) await deleteDisposableExtension(page, created)
  }
})

test('persists, edits, synchronizes, and clears the managed Extension Voicemail aggregate', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const number = Date.now().toString().slice(-8)
  const callbackNumber = `+1555${number}`
  const updatedCallbackNumber = `+1666${number}`
  let created: DisposableExtension | null = null

  try {
    await page.goto('/extensions')
    await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
    await page.getByRole('button', { name: 'Create extension' }).click()
    await page.getByLabel('First name').fill('GridPBX')
    await page.getByLabel('Last name').fill('Voicemail Aggregate')
    await page.getByLabel('Extension number').fill(number)

    const voicemailCard = page.locator('article').filter({ hasText: 'Voicemail fallback' })
    await voicemailCard.getByRole('button', { name: /Configure/ }).click()
    const voicemailDrawer = page.getByRole('dialog', { name: 'Configure voicemail' })
    await expect(page.getByRole('heading', { name: 'Configure voicemail' })).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(1)
    await expect
      .poll(() => page.getByTestId('slide-over-content').evaluate((element) => element.scrollTop))
      .toBe(0)

    await expect(voicemailDrawer.getByLabel('Mailbox name')).toHaveAttribute('readonly', '')
    await expect(voicemailDrawer.getByLabel('Mailbox number')).toHaveValue(number)
    await selectVoicemailSection(voicemailDrawer, 'Options')
    await voicemailDrawer
      .getByLabel('Notification email addresses')
      .fill('voicemail-audit@example.test')
    await voicemailDrawer.getByRole('button', { name: 'Advanced notification delivery' }).click()
    await expect(
      voicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }),
    ).toBeVisible()
    await voicemailDrawer.getByRole('switch', { name: 'Save after notification' }).click()
    await voicemailDrawer.getByRole('button', { name: 'Callback notification' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Configure callback notification' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Pause callback attempts' }).click()
    await voicemailDrawer.getByLabel('Callback number').fill(callbackNumber)
    await voicemailDrawer.getByRole('spinbutton', { name: 'Attempts' }).fill('4')
    await voicemailDrawer.getByRole('spinbutton', { name: 'Retry interval' }).fill('120')
    await voicemailDrawer.getByRole('spinbutton', { name: 'Answer timeout' }).fill('25')
    await voicemailDrawer.getByLabel('Callback schedule').fill('60, 180')
    await voicemailDrawer
      .getByText('Voicemail audio format')
      .locator('..')
      .getByRole('button')
      .click()
    await page.getByRole('option', { name: 'WAV', exact: true }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Require PIN' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Lock mailbox configuration' }).click()
    await voicemailDrawer.getByRole('button', { name: 'Playback behavior' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Play oldest messages first' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Skip message envelope' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Enable fast-forward and rewind' }).click()
    await voicemailDrawer.getByLabel('Seek duration').fill('15000')
    await selectVoicemailSection(voicemailDrawer, 'Basic')
    await voicemailDrawer.getByLabel('PIN', { exact: true }).fill(number.slice(-4))
    await voicemailDrawer.getByRole('button', { name: 'Use this mailbox' }).click()
    await expect(page.getByRole('heading', { name: 'Configure voicemail' })).toHaveCount(0)
    await expect(voicemailCard.getByText('Mailbox settings configured')).toBeVisible()

    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Create extension', exact: true }).last().click()
    const response = await createResponse
    if (response.status() !== 201) {
      throw new Error(`Disposable Extension + Voicemail creation failed: ${await response.text()}`)
    }

    const responseUrl = new URL(response.url())
    const result = (await response.json()) as {
      data: { id: string; voicemail_boxes: Array<{ id: string; mailbox: string | null }> }
    }
    const accountId = responseUrl.pathname.match(/\/accounts\/([^/]+)\//)?.[1]
    const mailboxId = result.data.voicemail_boxes.find((mailbox) => mailbox.mailbox === number)?.id
    if (!accountId || !mailboxId) {
      throw new Error('Unable to derive the account and managed mailbox identifiers.')
    }
    const voicemailDetailUrl = `${responseUrl.origin}/api/v1/accounts/${accountId}/voicemail-boxes/${mailboxId}`
    created = {
      deleteUrl: `${responseUrl.origin}${responseUrl.pathname}/${result.data.id}`,
      number,
    }

    await synchronizeExtensions(page, responseUrl.origin, accountId)
    const synchronizedCreate = await voicemailDetail(page, voicemailDetailUrl)
    expect(synchronizedCreate).toMatchObject({
      mailbox: number,
      notification_emails: ['voicemail-audit@example.test'],
      require_pin: true,
      pin_configured: true,
      configuration: {
        media_extension: 'wav',
        not_configurable: true,
        oldest_message_first: true,
        save_after_notify: true,
        delete_after_notify: false,
        skip_envelope: true,
        is_voicemail_ff_rw_enabled: true,
        seek_duration_ms: 15000,
        notify_callback: {
          disabled: true,
          number: callbackNumber,
          attempts: 4,
          interval_s: 120,
          timeout_s: 25,
          schedule: [60, 180],
        },
      },
    })
    await expect(
      page.getByRole('heading', { level: 1, name: 'GridPBX Voicemail Aggregate' }),
    ).toBeVisible()
    const voicemailBoxes = page.locator('article').filter({ hasText: 'Voicemail boxes' })
    await expect(voicemailBoxes.getByText(`Mailbox ${number}`, { exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Edit' }).click()
    await expect(page.getByRole('heading', { name: 'Edit extension' })).toBeVisible()
    const editVoicemailCard = page.locator('article').filter({ hasText: 'Voicemail fallback' })
    await editVoicemailCard.getByRole('button', { name: 'Configure' }).click()
    const editVoicemailDrawer = page.getByRole('dialog', { name: 'Configure voicemail' })
    await expect(page.getByRole('dialog')).toHaveCount(1)
    await expect
      .poll(() => page.getByTestId('slide-over-content').evaluate((element) => element.scrollTop))
      .toBe(0)
    await selectVoicemailSection(editVoicemailDrawer, 'Options')
    await expect(editVoicemailDrawer.getByLabel('Notification email addresses')).toHaveValue(
      'voicemail-audit@example.test',
    )
    await editVoicemailDrawer
      .getByLabel('Notification email addresses')
      .fill('voicemail-updated@example.test')
    await editVoicemailDrawer
      .getByRole('button', { name: 'Advanced notification delivery' })
      .click()
    await expect(
      editVoicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }),
    ).toBeChecked()
    await editVoicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }).click()
    await editVoicemailDrawer.getByRole('switch', { name: 'Save after notification' }).click()
    await editVoicemailDrawer.getByRole('switch', { name: 'Delete after notification' }).click()
    await editVoicemailDrawer.getByRole('button', { name: 'Callback notification' }).click()
    await expect(
      editVoicemailDrawer.getByRole('switch', { name: 'Configure callback notification' }),
    ).toBeChecked()
    await editVoicemailDrawer.getByRole('switch', { name: 'Pause callback attempts' }).click()
    await editVoicemailDrawer.getByLabel('Callback number').fill(updatedCallbackNumber)
    await editVoicemailDrawer
      .getByText('Voicemail audio format')
      .locator('..')
      .getByRole('button')
      .click()
    await page.getByRole('option', { name: 'MP4', exact: true }).click()
    await editVoicemailDrawer.getByRole('button', { name: 'Playback behavior' }).click()
    await editVoicemailDrawer.getByRole('switch', { name: 'Play oldest messages first' }).click()
    await editVoicemailDrawer.getByRole('switch', { name: 'Skip message envelope' }).click()
    await editVoicemailDrawer
      .getByRole('switch', { name: 'Enable fast-forward and rewind' })
      .click()
    await editVoicemailDrawer.getByRole('button', { name: 'Use this mailbox' }).click()

    const updateResponse = page.waitForResponse(
      (candidate) =>
        candidate.request().method() === 'PUT' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions\/[^/]+$/.test(new URL(candidate.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    expect((await updateResponse).status()).toBe(200)

    await synchronizeExtensions(page, responseUrl.origin, accountId)
    const synchronizedEdit = await voicemailDetail(page, voicemailDetailUrl)
    expect(synchronizedEdit).toMatchObject({
      notification_emails: ['voicemail-updated@example.test'],
      require_pin: true,
      pin_configured: true,
      configuration: {
        include_message_on_notify: false,
        media_extension: 'mp4',
        oldest_message_first: false,
        save_after_notify: false,
        delete_after_notify: true,
        skip_envelope: false,
        is_voicemail_ff_rw_enabled: false,
        notify_callback: {
          disabled: false,
          number: updatedCallbackNumber,
          attempts: 4,
          interval_s: 120,
          timeout_s: 25,
          schedule: [60, 180],
        },
      },
    })

    await page.reload()
    await page.getByRole('button', { name: 'Edit' }).click()
    await page
      .locator('article')
      .filter({ hasText: 'Voicemail fallback' })
      .getByRole('button', { name: 'Configure' })
      .click()
    const verifyVoicemailDrawer = page.getByRole('dialog', { name: 'Configure voicemail' })
    await selectVoicemailSection(verifyVoicemailDrawer, 'Options')
    await verifyVoicemailDrawer.getByLabel('Notification email addresses').fill('')
    await verifyVoicemailDrawer
      .getByRole('button', { name: 'Advanced notification delivery' })
      .click()
    await expect(
      verifyVoicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }),
    ).not.toBeChecked()
    await verifyVoicemailDrawer.getByRole('switch', { name: 'Delete after notification' }).click()
    await verifyVoicemailDrawer.getByRole('switch', { name: 'Include transcription' }).click()
    await verifyVoicemailDrawer.getByRole('button', { name: 'Callback notification' }).click()
    await expect(
      verifyVoicemailDrawer.getByRole('switch', { name: 'Configure callback notification' }),
    ).toBeChecked()
    await verifyVoicemailDrawer
      .getByRole('switch', { name: 'Configure callback notification' })
      .click()
    await verifyVoicemailDrawer
      .getByText('Voicemail audio format')
      .locator('..')
      .getByRole('button')
      .click()
    await page.getByRole('option', { name: 'MP3', exact: true }).click()
    await verifyVoicemailDrawer.getByRole('switch', { name: 'Require PIN' }).click()
    await verifyVoicemailDrawer.getByRole('switch', { name: 'Lock mailbox configuration' }).click()
    await verifyVoicemailDrawer.getByRole('button', { name: 'Use this mailbox' }).click()

    const clearResponse = page.waitForResponse(
      (candidate) =>
        candidate.request().method() === 'PUT' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions\/[^/]+$/.test(new URL(candidate.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    expect((await clearResponse).status()).toBe(200)

    await synchronizeExtensions(page, responseUrl.origin, accountId)
    const synchronizedClear = await voicemailDetail(page, voicemailDetailUrl)
    expect(synchronizedClear).toMatchObject({
      notification_emails: [],
      require_pin: false,
      pin_configured: false,
      configuration: {
        include_message_on_notify: false,
        include_transcription_on_notify: false,
        media_extension: 'mp3',
        not_configurable: false,
        save_after_notify: false,
        delete_after_notify: false,
        notify_callback: null,
      },
    })
    expect(issues).toEqual([])
  } finally {
    if (created) await deleteDisposableExtension(page, created)
  }
})

test('shows schema-backed managed User calling fields without clipping or leaking storage URLs', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  let created: { deleteUrl: string; number: string } | null = null
  await page.goto('/extensions')
  await expect(page.getByRole('heading', { name: 'People & Extensions' })).toBeVisible()
  const extensionHrefs = await page
    .getByRole('link', { name: /^View / })
    .evaluateAll((links) => links.map((link) => (link as HTMLAnchorElement).href))
  let managedExtensionFound = false

  for (const href of extensionHrefs) {
    await page.goto(href)
    if ((await page.getByRole('button', { name: 'Edit' }).count()) > 0) {
      managedExtensionFound = true
      break
    }
  }

  if (!managedExtensionFound) {
    const number = Date.now().toString().slice(-8)
    await page.goto('/extensions')
    await page.getByRole('button', { name: 'Create extension' }).click()
    await page.getByLabel('First name').fill('GridPBX')
    await page.getByLabel('Last name').fill('Calling Audit')
    await page.getByLabel('Extension number').fill(number)
    const voicemail = page.locator('article').filter({ hasText: 'Voicemail fallback' })
    await voicemail.getByRole('switch', { name: 'Create' }).click()
    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Create extension', exact: true }).last().click()
    const response = await createResponse
    if (response.status() !== 201) {
      throw new Error(`Disposable Extension creation failed: ${await response.text()}`)
    }
    const responseUrl = new URL(response.url())
    const result = (await response.json()) as { data: { id: string } }
    created = {
      deleteUrl: `${responseUrl.origin}${responseUrl.pathname}/${result.data.id}`,
      number,
    }
  }

  try {
    await expect(page.getByRole('button', { name: 'Edit' })).toBeVisible()
    await page.getByRole('button', { name: 'Edit' }).click()
    await page.waitForTimeout(300)
    const formSections = page.getByRole('tablist', { name: 'Extension form sections' })
    await formSections.getByRole('tab', { name: 'Advanced' }).click()
    const advancedSections = page.getByRole('tablist', { name: 'Extension advanced sections' })
    if ((await advancedSections.count()) === 0) {
      throw new Error(`Extension editor did not mount: ${issues.join(' | ')}`)
    }

    await advancedSections.getByRole('tab', { name: 'Caller ID' }).click()
    await expect(page.getByRole('heading', { name: 'Caller ID', exact: true })).toBeVisible()
    await expect(page.getByText('asserted identity remains Switch-managed')).toBeVisible()

    const externalCallerId = page.getByText('External caller-ID number').locator('..')
    await externalCallerId.getByRole('button').click()
    const listbox = page.getByRole('listbox')
    await expect(listbox).toBeVisible()
    const box = await listbox.boundingBox()
    const viewport = page.viewportSize()
    expect(box).not.toBeNull()
    expect(viewport).not.toBeNull()
    expect(box!.x).toBeGreaterThanOrEqual(0)
    expect(box!.y).toBeGreaterThanOrEqual(0)
    expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
    expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
    await page.getByRole('option', { name: 'Use account caller ID' }).click()

    await advancedSections.getByRole('tab', { name: 'Call Forward' }).click()
    const forwarding = page.getByRole('switch', { name: 'Enable call forwarding' })
    if (!(await forwarding.isChecked())) await forwarding.click()
    const destination = page.getByText('Forwarding destination').locator('..').locator('input')
    await destination.fill('')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await page.waitForTimeout(100)
    if ((await destination.getAttribute('aria-invalid')) !== 'true') {
      throw new Error(
        `Forwarding error was not attached to its input: ${(await page.locator('.text-danger').allTextContents()).join(' | ')}`,
      )
    }
    await expect(destination).toHaveAttribute('aria-invalid', 'true')
    await expect(destination).toHaveClass(/border-red-400/)
    await expect(page.getByText('Enter a forwarding destination.')).toBeVisible()
    await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)

    await forwarding.click()
    await advancedSections.getByRole('tab', { name: 'Recording' }).click()
    await expect(page.getByRole('heading', { name: 'User call recording' })).toBeVisible()
    await expect(page.getByText(/recording.*url/i)).toBeVisible()
    await expect(page.getByText('https://', { exact: false })).toHaveCount(0)

    await advancedSections.getByRole('tab', { name: 'Options' }).click()
    await expect(page.getByRole('heading', { name: 'Music on hold' })).toBeVisible()
    await page.getByRole('button', { name: 'Select extension music on hold' }).click()
    const musicListbox = page.getByRole('listbox')
    await expect(musicListbox).toBeVisible()
    const musicBox = await musicListbox.boundingBox()
    expect(musicBox).not.toBeNull()
    expect(musicBox!.x).toBeGreaterThanOrEqual(0)
    expect(musicBox!.y).toBeGreaterThanOrEqual(0)
    expect(musicBox!.x + musicBox!.width).toBeLessThanOrEqual(viewport!.width)
    expect(musicBox!.y + musicBox!.height).toBeLessThanOrEqual(viewport!.height)
    await page.getByRole('option', { name: 'Inherit account music' }).click()

    await advancedSections.getByRole('tab', { name: 'Media' }).click()
    await expect(page.getByRole('heading', { name: 'Media and endpoint audio' })).toBeVisible()
    await page.getByRole('button', { name: 'Codec, transport, and ringtone controls' }).click()
    await expect(page.getByText('Audio codec priority', { exact: true })).toBeVisible()
    const progressTimeout = page.getByLabel('Progress timeout (seconds)')
    await progressTimeout.fill('3601')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expect(progressTimeout).toHaveAttribute('aria-invalid', 'true')
    await expect(progressTimeout).toHaveClass(/border-red-400/)

    await progressTimeout.fill('30')
    await advancedSections.getByRole('tab', { name: 'Routing & Profile' }).click()
    await expect(page.getByRole('heading', { name: 'Routing and directory profile' })).toBeVisible()
    await page.getByRole('button', { name: 'Dial plan', exact: true }).click()
    await page.getByRole('button', { name: 'Add rule' }).click()
    const dialPlanPattern = page.getByText('Regex pattern').locator('..').locator('input').last()
    await dialPlanPattern.fill('(?R)')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expect(dialPlanPattern).toHaveAttribute('aria-invalid', 'true')
    await expect(dialPlanPattern).toHaveClass(/border-red-400/)

    await page.getByRole('button', { name: 'Directory profile and spoken name' }).click()
    await page.getByRole('button', { name: 'Select pronounced-name media' }).click()
    const pronouncedNameListbox = page.getByRole('listbox')
    await expect(pronouncedNameListbox).toBeVisible()
    const pronouncedNameBox = await pronouncedNameListbox.boundingBox()
    expect(pronouncedNameBox).not.toBeNull()
    expect(pronouncedNameBox!.x).toBeGreaterThanOrEqual(0)
    expect(pronouncedNameBox!.y).toBeGreaterThanOrEqual(0)
    expect(pronouncedNameBox!.x + pronouncedNameBox!.width).toBeLessThanOrEqual(viewport!.width)
    expect(pronouncedNameBox!.y + pronouncedNameBox!.height).toBeLessThanOrEqual(viewport!.height)
    await page.getByRole('option', { name: 'No pronounced-name media' }).click()
    await expect(page.getByText('Switch-managed policy')).toBeVisible()
    expect(issues).toEqual([])
  } finally {
    if (created) await deleteDisposableExtension(page, created)
  }
})

test('keeps Voicemail validation inline and its assignment listbox inside the viewport', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/voicemail')
  await expect(page.getByRole('heading', { name: 'Voicemail boxes' })).toBeVisible()
  await page.getByRole('link', { name: 'Add mailbox' }).click()
  await expect(page.getByRole('heading', { name: 'Add voicemail box' })).toBeVisible()

  const assignment = page.locator('article').filter({ hasText: 'Assignment' })
  await assignment.getByRole('button').click()
  const options = page.getByRole('listbox')
  await expect(options).toBeVisible()
  const box = await options.boundingBox()
  const viewport = page.viewportSize()
  expect(box).not.toBeNull()
  expect(viewport).not.toBeNull()
  expect(box!.x).toBeGreaterThanOrEqual(0)
  expect(box!.y).toBeGreaterThanOrEqual(0)
  expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width)
  expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height)
  await page.getByRole('option', { name: 'Unassigned', exact: true }).click()

  const formSections = page.getByRole('tablist', { name: 'Form sections' })
  const advancedSections = page.getByRole('tablist', { name: 'Voicemail advanced sections' })
  await expect(formSections.getByRole('tab')).toHaveText(['Basic', 'Advanced'])
  await selectVoicemailSection(page, 'Options')
  await expect(advancedSections.getByRole('tab')).toHaveText(['Basic', 'Options'])
  await page.getByRole('button', { name: 'Timezone' }).click()
  await expect(page.getByRole('option', { name: /Account default/ })).toBeVisible()
  await page.getByRole('option', { name: /Account default/ }).click()

  const features = page.locator('article').filter({ hasText: 'Features' })
  await features.getByRole('switch', { name: 'Require PIN' }).click()

  await page.getByRole('button', { name: 'Callback notification' }).click()
  await page.getByRole('switch', { name: 'Configure callback notification' }).click()

  await page.getByRole('button', { name: 'Create mailbox' }).click()
  const mailboxName = page.getByLabel('Mailbox name')
  await expect(mailboxName).toHaveAttribute('aria-invalid', 'true')
  await expect(mailboxName).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a mailbox name.')).toBeVisible()
  const pin = page.locator('article').filter({ hasText: 'Mailbox PIN' }).locator('input')
  await expect(pin).toHaveAttribute('aria-invalid', 'true')
  await expect(pin).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a mailbox PIN when PIN protection is enabled.')).toBeVisible()
  await selectVoicemailSection(page, 'Options')
  const callbackNumber = page.getByRole('textbox', { name: 'Callback number' })
  await expect(callbackNumber).toHaveAttribute('aria-invalid', 'true')
  await expect(callbackNumber).toHaveClass(/border-red-400/)
  await expect(
    page.getByText('Enter a callback number when callback notifications are enabled.'),
  ).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('uses the same Voicemail tab hierarchy in the embedded Extension form', async ({ page }) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/(?:extensions|voicemail-boxes)(?:\/|$)/.test(request.url())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/extensions')
  await page.getByRole('button', { name: 'Create extension' }).click()
  const voicemailCard = page.locator('article').filter({ hasText: 'Voicemail fallback' })
  await voicemailCard.getByRole('button', { name: /Configure/ }).click()
  const drawer = page.getByRole('dialog', { name: 'Configure voicemail' })

  await expect(drawer.getByRole('tablist', { name: 'Form sections' }).getByRole('tab')).toHaveText([
    'Basic',
    'Advanced',
  ])
  await selectVoicemailSection(drawer, 'Options')
  await expect(
    drawer.getByRole('tablist', { name: 'Voicemail advanced sections' }).getByRole('tab'),
  ).toHaveText(['Basic', 'Options'])
  await expect(drawer.getByRole('button', { name: 'Timezone' })).toBeVisible()
  await expect(drawer.getByLabel('Notification email addresses')).toBeVisible()
  await drawer.getByRole('button', { name: 'Back to extension' }).click()

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('reports unavailable voicemail transcription without allowing a mutation', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\/voicemail-boxes(?:\/|$)/.test(request.url())
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })

  await page.goto('/voicemail')
  await expect(page.getByRole('heading', { name: 'Voicemail boxes' })).toBeVisible()
  await page.getByRole('link', { name: 'Add mailbox' }).click()
  await expect(page.getByRole('heading', { name: 'Add voicemail box' })).toBeVisible()

  await selectVoicemailSection(page, 'Options')
  const features = page.locator('article').filter({ hasText: 'Features' })
  await expect(features.getByRole('switch', { name: 'Transcribe messages' })).toBeDisabled()
  await expect(
    features.getByText('Voicemail transcription is unavailable on this Switch cluster.'),
  ).toBeVisible()
  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})

test('preserves a write-only PIN while editing and clearing a Voicemail callback', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const mailbox = Date.now().toString().slice(-9)
  const name = `GridPBX callback audit ${mailbox}`
  let createdId: string | null = null

  try {
    await page.goto('/voicemail')
    await page.getByRole('link', { name: 'Add mailbox' }).click()
    await page.getByLabel('Mailbox name').fill(name)
    await page.getByLabel('Mailbox number').fill(mailbox)
    await selectVoicemailSection(page, 'Options')
    await page.getByRole('switch', { name: 'Require PIN' }).click()
    await selectVoicemailSection(page, 'Basic')
    await page.getByLabel('PIN', { exact: true }).fill('246810')
    await selectVoicemailSection(page, 'Options')
    await page.getByRole('button', { name: 'Callback notification' }).click()
    await page.getByRole('switch', { name: 'Configure callback notification' }).click()
    await page.getByRole('switch', { name: 'Pause callback attempts' }).click()
    await page.getByRole('textbox', { name: 'Callback number' }).fill('+15559876543')

    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/voicemail-boxes$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Create mailbox' }).click()
    const createdResponse = await createResponse
    expect(createdResponse.status()).toBe(201)
    createdId = ((await createdResponse.json()) as { data: { id: string } }).data.id
    await expect(page.getByRole('heading', { name })).toBeVisible()

    await page.getByRole('link', { name: 'Edit' }).click()
    await expect(page.getByLabel('Mailbox name')).toHaveValue(name)
    await selectVoicemailSection(page, 'Options')
    await expect(page.getByRole('switch', { name: 'Require PIN' })).toBeChecked()
    await selectVoicemailSection(page, 'Basic')
    await expect(page.getByLabel('New PIN')).toHaveValue('')
    await expect(page.getByText('Leave blank to keep the existing PIN.')).toBeVisible()
    await selectVoicemailSection(page, 'Options')
    await page.getByRole('button', { name: 'Callback notification' }).click()
    await expect(
      page.getByRole('switch', { name: 'Configure callback notification' }),
    ).toBeChecked()
    await expect(page.getByRole('switch', { name: 'Pause callback attempts' })).toBeChecked()
    await expect(page.getByRole('textbox', { name: 'Callback number' })).toHaveValue('+15559876543')
    await page.getByRole('textbox', { name: 'Callback number' }).fill('+15559876544')

    const editResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        /\/api\/v1\/accounts\/[^/]+\/voicemail-boxes\/[^/]+$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    const editedResponse = await editResponse
    if (editedResponse.status() !== 200) {
      throw new Error(`Voicemail edit failed: ${await editedResponse.text()}`)
    }

    await page.getByRole('link', { name: 'Edit' }).click()
    await expect(page.getByLabel('Mailbox name')).toHaveValue(name)
    await selectVoicemailSection(page, 'Options')
    await expect(page.getByRole('switch', { name: 'Require PIN' })).toBeChecked()
    await selectVoicemailSection(page, 'Basic')
    await expect(page.getByLabel('New PIN')).toHaveValue('')
    await expect(page.getByText('Leave blank to keep the existing PIN.')).toBeVisible()
    await selectVoicemailSection(page, 'Options')
    await page.getByRole('button', { name: 'Callback notification' }).click()
    await page.getByRole('switch', { name: 'Configure callback notification' }).click()

    const clearResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        /\/api\/v1\/accounts\/[^/]+\/voicemail-boxes\/[^/]+$/.test(
          new URL(response.url()).pathname,
        ),
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    const clearedResponse = await clearResponse
    if (clearedResponse.status() !== 200) {
      throw new Error(`Voicemail callback clear failed: ${await clearedResponse.text()}`)
    }

    await page.getByRole('link', { name: 'Edit' }).click()
    await expect(page.getByLabel('Mailbox name')).toHaveValue(name)
    await selectVoicemailSection(page, 'Options')
    await expect(page.getByRole('switch', { name: 'Require PIN' })).toBeChecked()
    await selectVoicemailSection(page, 'Basic')
    await expect(page.getByLabel('New PIN')).toHaveValue('')
    await selectVoicemailSection(page, 'Options')
    await page.getByRole('button', { name: 'Callback notification' }).click()
    await expect(
      page.getByRole('switch', { name: 'Configure callback notification' }),
    ).not.toBeChecked()
    await expect(page.getByRole('textbox', { name: 'Callback number' })).toHaveCount(0)
    await page.getByRole('button', { name: 'Close panel' }).click()

    expect(issues).toEqual([])
  } finally {
    if (createdId) {
      await page.goto(`/voicemail/${createdId}`)
      await expect(page.getByRole('heading', { name })).toBeVisible()
      page.once('dialog', (dialog) => dialog.accept())
      const deleteResponse = page.waitForResponse(
        (response) =>
          response.request().method() === 'DELETE' &&
          /\/api\/v1\/accounts\/[^/]+\/voicemail-boxes\/[^/]+$/.test(
            new URL(response.url()).pathname,
          ),
      )
      await page.getByRole('button', { name: 'Delete', exact: true }).click()
      expect((await deleteResponse).status()).toBe(204)
    }
  }
})

test('creates a Switch user with the complete tabbed advanced schema', async ({ page }) => {
  const issues = collectPageIssues(page)
  const listResponsePromise = page.waitForResponse((response) =>
    /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
  )
  await page.goto('/extensions')
  const listResponse = await listResponsePromise
  const listUrl = new URL(listResponse.url())
  const accountId = listUrl.pathname.match(/\/accounts\/([^/]+)\/extensions/)?.[1]
  if (!accountId) throw new Error('Unable to resolve the selected account.')

  const number = Date.now().toString().slice(-8)
  const apiOrigin = listUrl.origin
  let created: DisposableExtension | null = null

  await page.getByRole('button', { name: 'Create extension' }).click()
  const drawer = page.getByRole('dialog', { name: 'Create extension' })
  await drawer.getByLabel('First name').fill('GridPBX')
  await drawer.getByLabel('Last name').fill('Advanced Create')
  await drawer.getByLabel('Extension number').fill(number)
  await drawer
    .locator('article')
    .filter({ hasText: 'Voicemail fallback' })
    .getByRole('switch', { name: 'Create' })
    .click()

  await drawer.getByRole('tab', { name: 'Advanced' }).click()
  const advancedTabs = drawer.getByRole('tablist', { name: 'Extension advanced sections' })
  await expect(advancedTabs.getByRole('tab', { name: 'Media' })).toBeVisible()
  await expect(advancedTabs.getByRole('tab', { name: 'Routing & Profile' })).toBeVisible()
  await expect(advancedTabs.getByRole('tab', { name: 'Metaflows' })).toBeVisible()

  try {
    await advancedTabs.getByRole('tab', { name: 'Media' }).click()
    await drawer.getByRole('button', { name: 'Codec, transport, and ringtone controls' }).click()
    await drawer.getByLabel('Progress timeout (seconds)').fill('30')
    await drawer.getByLabel(/internal ringtone header/i).fill('GridPBX-internal')
    await drawer.getByLabel(/external ringtone header/i).fill('GridPBX-external')

    await advancedTabs.getByRole('tab', { name: 'Routing & Profile' }).click()
    await drawer.getByRole('button', { name: 'Directory profile and spoken name' }).click()
    await drawer.getByLabel('Title').fill('Advanced Create Verification')
    await drawer.getByLabel('Profile role').fill('Operator')
    await drawer.getByLabel('Nicknames').fill('Advanced')
    await drawer.getByLabel('Profile note').fill('Created through the GridPBX user interface.')

    await advancedTabs.getByRole('tab', { name: 'Metaflows' }).click()
    await drawer.getByRole('button', { name: 'User metaflow binding digit' }).click()
    await page.getByRole('option', { name: '#', exact: true }).click()
    await drawer.getByLabel('Digit timeout (ms)').fill('2500')
    await drawer.getByRole('button', { name: 'User metaflow listen on' }).click()
    await page.getByRole('option', { name: 'Originating leg', exact: true }).click()

    const createResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions$/.test(new URL(response.url()).pathname),
    )
    await drawer.getByRole('button', { name: 'Create extension', exact: true }).click()
    const response = await createResponse
    if (response.status() !== 201) {
      throw new Error(`Advanced Extension creation failed: ${await response.text()}`)
    }

    const result = (await response.json()) as { data: { id: string } }
    created = {
      deleteUrl: `${apiOrigin}/api/v1/accounts/${accountId}/extensions/${result.data.id}`,
      number,
    }
    await synchronizeExtensions(page, apiOrigin, accountId)

    const readConfiguration = async () => {
      const detail = await authenticatedJson<{
        data: {
          configuration: {
            media: { progress_timeout: number | null }
            ringtones: { internal: string | null; external: string | null }
            profile: {
              title: string | null
              role: string | null
              nicknames: string[]
              note: string | null
            }
            metaflows: {
              binding_digit: string | null
              digit_timeout: number | null
              listen_on: string | null
            }
          }
        }
      }>(page, created!.deleteUrl)
      expect(detail.status).toBe(200)

      return detail.data.data.configuration
    }

    expect(await readConfiguration()).toMatchObject({
      media: { progress_timeout: 30 },
      ringtones: { internal: 'GridPBX-internal', external: 'GridPBX-external' },
      profile: {
        title: 'Advanced Create Verification',
        role: 'Operator',
        nicknames: ['Advanced'],
        note: 'Created through the GridPBX user interface.',
      },
      metaflows: { binding_digit: '#', digit_timeout: 2500, listen_on: 'self' },
    })

    await page.goto(`/extensions/${result.data.id}`)
    await page.getByRole('button', { name: 'Edit' }).click()
    const editDrawer = page.getByRole('dialog', { name: 'Edit extension' })
    await editDrawer.getByRole('tab', { name: 'Advanced' }).click()
    const editTabs = editDrawer.getByRole('tablist', { name: 'Extension advanced sections' })

    await editTabs.getByRole('tab', { name: 'Media' }).click()
    await editDrawer
      .getByRole('button', { name: 'Codec, transport, and ringtone controls' })
      .click()
    await expect(editDrawer.getByLabel('Progress timeout (seconds)')).toHaveValue('30')
    await editDrawer.getByLabel('Progress timeout (seconds)').fill('45')
    await editDrawer.getByLabel(/internal ringtone header/i).fill('GridPBX-internal-edited')
    await editDrawer.getByLabel(/external ringtone header/i).fill('GridPBX-external-edited')

    await editTabs.getByRole('tab', { name: 'Routing & Profile' }).click()
    await editDrawer.getByRole('button', { name: 'Directory profile and spoken name' }).click()
    await expect(editDrawer.getByLabel('Title')).toHaveValue('Advanced Create Verification')
    await editDrawer.getByLabel('Title').fill('Advanced Edit Verification')
    await editDrawer.getByLabel('Profile role').fill('Supervisor')
    await editDrawer.getByLabel('Nicknames').fill('Advanced, Live')
    await editDrawer.getByLabel('Profile note').fill('Edited through the GridPBX user interface.')

    await editTabs.getByRole('tab', { name: 'Metaflows' }).click()
    await editDrawer.getByRole('button', { name: 'User metaflow binding digit' }).click()
    await page.getByRole('option', { name: '*', exact: true }).click()
    await editDrawer.getByLabel('Digit timeout (ms)').fill('3000')
    await editDrawer.getByRole('button', { name: 'User metaflow listen on' }).click()
    await page.getByRole('option', { name: 'Both call legs', exact: true }).click()

    const updateResponse = page.waitForResponse(
      (candidate) =>
        candidate.request().method() === 'PUT' && candidate.url() === created!.deleteUrl,
    )
    await editDrawer.getByRole('button', { name: 'Save changes' }).click()
    expect((await updateResponse).status()).toBe(200)
    await synchronizeExtensions(page, apiOrigin, accountId)
    expect(await readConfiguration()).toMatchObject({
      media: { progress_timeout: 45 },
      ringtones: {
        internal: 'GridPBX-internal-edited',
        external: 'GridPBX-external-edited',
      },
      profile: {
        title: 'Advanced Edit Verification',
        role: 'Supervisor',
        nicknames: ['Advanced', 'Live'],
        note: 'Edited through the GridPBX user interface.',
      },
      metaflows: { binding_digit: '*', digit_timeout: 3000, listen_on: 'both' },
    })

    await page.reload()
    await page.getByRole('button', { name: 'Edit' }).click()
    const clearDrawer = page.getByRole('dialog', { name: 'Edit extension' })
    await clearDrawer.getByRole('tab', { name: 'Advanced' }).click()
    const clearTabs = clearDrawer.getByRole('tablist', { name: 'Extension advanced sections' })

    await clearTabs.getByRole('tab', { name: 'Media' }).click()
    await clearDrawer
      .getByRole('button', { name: 'Codec, transport, and ringtone controls' })
      .click()
    await clearDrawer.getByLabel('Progress timeout (seconds)').fill('')
    await clearDrawer.getByLabel(/internal ringtone header/i).fill('')
    await clearDrawer.getByLabel(/external ringtone header/i).fill('')

    await clearTabs.getByRole('tab', { name: 'Routing & Profile' }).click()
    await clearDrawer.getByRole('button', { name: 'Directory profile and spoken name' }).click()
    await clearDrawer.getByLabel('Title').fill('')
    await clearDrawer.getByLabel('Profile role').fill('')
    await clearDrawer.getByLabel('Nicknames').fill('')
    await clearDrawer.getByLabel('Profile note').fill('')

    await clearTabs.getByRole('tab', { name: 'Metaflows' }).click()
    await clearDrawer.getByRole('button', { name: 'User metaflow binding digit' }).click()
    await page.getByRole('option', { name: 'Use Switch default (*)', exact: true }).click()
    await clearDrawer.getByLabel('Digit timeout (ms)').fill('')
    await clearDrawer.getByRole('button', { name: 'User metaflow listen on' }).click()
    await page.getByRole('option', { name: 'Use Switch default', exact: true }).click()

    const clearResponse = page.waitForResponse(
      (candidate) =>
        candidate.request().method() === 'PUT' && candidate.url() === created!.deleteUrl,
    )
    await clearDrawer.getByRole('button', { name: 'Save changes' }).click()
    const clearedResponse = await clearResponse
    expect(clearedResponse.status()).toBe(200)
    expect(
      (
        clearedResponse.request().postDataJSON() as {
          metaflows: {
            binding_digit: string | null
            digit_timeout: number | null
            listen_on: string | null
          }
        }
      ).metaflows,
    ).toMatchObject({ binding_digit: null, digit_timeout: null, listen_on: null })
    await synchronizeExtensions(page, apiOrigin, accountId)
    expect(await readConfiguration()).toMatchObject({
      media: { progress_timeout: null },
      ringtones: { internal: null, external: null },
      profile: { title: null, role: null, nicknames: [], note: null },
      metaflows: { binding_digit: '*', digit_timeout: null, listen_on: null },
    })
    expect(issues).toEqual([])
  } finally {
    if (created) await deleteDisposableExtension(page, created)
  }
})
