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

type DisposableExtension = {
  deleteUrl: string
  number: string
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
  const password = credentials.getByLabel('Password', { exact: true })
  const confirmation = credentials.getByLabel('Confirm password')
  await password.fill('short')
  await confirmation.fill('different-password')

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
  await expect(credentials.getByText('Use at least 6 characters.')).toBeVisible()
  await expect(credentials.getByText('Passwords do not match.')).toBeVisible()
  await expect(credentials.locator('input[type="password"]').first()).toHaveClass(/border-red-400/)
  await expect(hotdesk.getByText('Use 4–15 dial-pad characters.')).toBeVisible()
  await expect(hotdeskId).toHaveClass(/border-red-400/)
  await expect(
    hotdesk.getByText('Enter a hotdesk PIN when PIN protection is enabled.'),
  ).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
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

test('configures a full managed Voicemail subview and removes the disposable aggregate', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const number = Date.now().toString().slice(-8)
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
    await voicemailDrawer
      .getByLabel('Notification email addresses')
      .fill('voicemail-audit@example.test')
    await voicemailDrawer.getByRole('button', { name: 'Advanced notification delivery' }).click()
    await expect(
      voicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }),
    ).toBeVisible()
    await voicemailDrawer.getByRole('button', { name: 'Callback notification' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Configure callback notification' }).click()
    await voicemailDrawer.getByRole('switch', { name: 'Pause callback attempts' }).click()
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
    created = {
      deleteUrl: `${responseUrl.origin}${responseUrl.pathname}/${result.data.id}`,
      number,
    }

    expect(result.data.voicemail_boxes.some((mailbox) => mailbox.mailbox === number)).toBe(true)
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
    await expect(editVoicemailDrawer.getByLabel('Notification email addresses')).toHaveValue(
      'voicemail-audit@example.test',
    )
    await editVoicemailDrawer
      .getByRole('button', { name: 'Advanced notification delivery' })
      .click()
    await expect(
      editVoicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }),
    ).toBeChecked()
    await editVoicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }).click()
    await editVoicemailDrawer.getByRole('button', { name: 'Callback notification' }).click()
    await expect(
      editVoicemailDrawer.getByRole('switch', { name: 'Configure callback notification' }),
    ).toBeChecked()
    await editVoicemailDrawer
      .getByRole('switch', { name: 'Configure callback notification' })
      .click()
    await editVoicemailDrawer.getByRole('button', { name: 'Use this mailbox' }).click()

    const updateResponse = page.waitForResponse(
      (candidate) =>
        candidate.request().method() === 'PUT' &&
        /\/api\/v1\/accounts\/[^/]+\/extensions\/[^/]+$/.test(new URL(candidate.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save changes' }).click()
    expect((await updateResponse).status()).toBe(200)

    await page.getByRole('button', { name: 'Edit' }).click()
    await page
      .locator('article')
      .filter({ hasText: 'Voicemail fallback' })
      .getByRole('button', { name: 'Configure' })
      .click()
    const verifyVoicemailDrawer = page.getByRole('dialog', { name: 'Configure voicemail' })
    await verifyVoicemailDrawer
      .getByRole('button', { name: 'Advanced notification delivery' })
      .click()
    await expect(
      verifyVoicemailDrawer.getByRole('switch', { name: 'Attach voicemail audio' }),
    ).not.toBeChecked()
    await verifyVoicemailDrawer.getByRole('button', { name: 'Callback notification' }).click()
    await expect(
      verifyVoicemailDrawer.getByRole('switch', { name: 'Configure callback notification' }),
    ).not.toBeChecked()
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
    if ((await page.getByRole('heading', { name: 'Caller ID and forwarding' }).count()) === 0) {
      throw new Error(`Extension editor did not mount: ${issues.join(' | ')}`)
    }

    await expect(page.getByRole('heading', { name: 'Caller ID and forwarding' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Call restrictions' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'User call recording' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Media and endpoint audio' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Routing and directory profile' })).toBeVisible()
    await expect(page.getByText('asserted identity remains Switch-managed')).toBeVisible()
    await expect(page.getByText(/recording.*url/i)).toBeVisible()
    await expect(page.getByText('https://', { exact: false })).toHaveCount(0)

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

    await page.getByRole('button', { name: 'Codec, transport, and ringtone controls' }).click()
    await expect(page.getByText('Audio codec priority', { exact: true })).toBeVisible()
    const progressTimeout = page.getByLabel('Progress timeout (seconds)')
    await progressTimeout.fill('3601')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expect(progressTimeout).toHaveAttribute('aria-invalid', 'true')
    await expect(progressTimeout).toHaveClass(/border-red-400/)

    await progressTimeout.fill('30')
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
  const callbackNumber = page.getByRole('textbox', { name: 'Callback number' })
  await expect(callbackNumber).toHaveAttribute('aria-invalid', 'true')
  await expect(callbackNumber).toHaveClass(/border-red-400/)
  await expect(
    page.getByText('Enter a callback number when callback notifications are enabled.'),
  ).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('creates, edits, clears, and removes a paused Voicemail callback configuration', async ({
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
    expect((await editResponse).status()).toBe(200)

    await page.getByRole('link', { name: 'Edit' }).click()
    await expect(page.getByLabel('Mailbox name')).toHaveValue(name)
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
    expect((await clearResponse).status()).toBe(200)

    await page.getByRole('link', { name: 'Edit' }).click()
    await expect(page.getByLabel('Mailbox name')).toHaveValue(name)
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
