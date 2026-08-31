import { expect, test, type Page, type TestInfo } from '@playwright/test'

type BrowserIssue = {
  kind: 'console' | 'page' | 'response'
  message: string
}

type EnrollmentState = {
  status: 'not_enrolled' | 'enrolled'
  provider: string | null
  eligible: boolean
  adapter_available: boolean
  can_enroll: boolean
  can_detach: boolean
  reason: string | null
  enrolled_at: string | null
  detached_at: string | null
}

type LineKeyPreview = {
  device: { id: string }
  capability: {
    model: {
      matched: boolean
      max_keys: number | null
      max_expansion_modules: number | null
      keys_per_expansion_module: number | null
    }
  }
  value_choices: Array<{
    id: string
    label: string
    value: string
    description: string | null
    types: Array<'presence' | 'personal_parking' | 'speed_dial'>
  }>
}

function observeBrowserIssues(page: Page): BrowserIssue[] {
  const issues: BrowserIssue[] = []

  page.on('console', (message) => {
    if (message.type() === 'error') {
      issues.push({ kind: 'console', message: message.text() })
    }
  })
  page.on('pageerror', (error) => {
    issues.push({ kind: 'page', message: error.message })
  })
  page.on('response', (response) => {
    const pathname = new URL(response.url()).pathname

    if (pathname.startsWith('/api/') && response.status() >= 400) {
      issues.push({
        kind: 'response',
        message: `${response.request().method()} ${pathname} returned ${response.status()}`,
      })
    }
  })

  return issues
}

function forbiddenEnrollmentKeys(value: unknown, path = 'data'): string[] {
  if (Array.isArray(value)) {
    return value.flatMap((item, index) => forbiddenEnrollmentKeys(item, `${path}.${index}`))
  }

  if (value === null || typeof value !== 'object') return []

  return Object.entries(value).flatMap(([key, child]) => {
    const currentPath = `${path}.${key}`
    const forbidden = ['access_token', 'credentials', 'password', 'secret', 'token'].includes(
      key.toLowerCase(),
    )

    return [...(forbidden ? [currentPath] : []), ...forbiddenEnrollmentKeys(child, currentPath)]
  })
}

async function attachScreenshot(page: Page, testInfo: TestInfo, name: string): Promise<void> {
  const path = testInfo.outputPath(`${name}.png`)

  await page.screenshot({ path })
  await testInfo.attach(name, { path, contentType: 'image/png' })
}

async function createProvisionedDevice(page: Page, name: string, mac: string): Promise<string> {
  await page.goto('/devices')
  await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()
  await page.getByRole('link', { name: 'Add device' }).click()
  await page.getByLabel('Device name').fill(name)

  await page.getByRole('button', { name: 'Select device brand' }).click()
  await page.getByRole('option', { name: 'Yealink' }).click()
  await page.getByRole('button', { name: 'Select device family' }).click()
  await page.getByRole('option', { name: 'T5 Series' }).click()
  await page.getByRole('button', { name: 'Select device model' }).click()
  await page.getByRole('option', { name: 'T54W' }).click()
  await page.getByLabel('MAC address').fill(mac)

  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: 'SIP', exact: true }).click()
  await page.getByLabel('SIP username').fill(`e2e${Date.now().toString().slice(-10)}`)
  await page.getByLabel('SIP password').fill('E2E-device-pass-123')

  const creation = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      /\/api\/v1\/accounts\/[^/]+\/devices$/.test(new URL(response.url()).pathname),
  )
  await page.getByRole('button', { name: 'Create device' }).click()
  const response = await creation

  expect(response.status()).toBe(201)

  return ((await response.json()) as { data: { id: string } }).data.id
}

async function deleteDevice(page: Page, deviceId: string): Promise<void> {
  await page.goto(`/devices/${deviceId}`)
  const deleteButton = page.getByRole('button', { name: 'Delete', exact: true })
  await expect(deleteButton).toBeVisible()

  const deletion = page.waitForResponse(
    (response) =>
      response.request().method() === 'DELETE' &&
      new URL(response.url()).pathname.endsWith(`/devices/${deviceId}`),
  )
  await deleteButton.click()
  await page.getByRole('button', { name: 'Delete device' }).click()
  expect((await deletion).status()).toBe(204)
}

async function cleanupWalkthroughDevices(page: Page): Promise<void> {
  const fixtureName = 'E2E provisioning walkthrough'
  await page.goto('/devices')
  await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()

  const results = page.waitForResponse((response) => {
    const url = new URL(response.url())

    return (
      response.request().method() === 'GET' &&
      /\/api\/v1\/accounts\/[^/]+\/devices$/.test(url.pathname) &&
      url.searchParams.get('search') === fixtureName
    )
  })
  const search = page.getByPlaceholder('Search name, model, MAC, extension…')
  await search.fill(fixtureName)
  await search.press('Enter')
  const devices = ((await (await results).json()).data ?? []) as Array<{ id: string }>

  for (const device of devices) await deleteDevice(page, device.id)
}

test('walks through provisioning and line-key create, edit, and clear in isolation', async ({
  page,
}, testInfo) => {
  test.setTimeout(90_000)
  await page.setViewportSize({ width: 1024, height: 768 })

  const issues = observeBrowserIssues(page)
  const suffix = Date.now().toString(16).slice(-8).padStart(8, '0')
  const mac = `02:00:${suffix.slice(0, 2)}:${suffix.slice(2, 4)}:${suffix.slice(4, 6)}:${suffix.slice(6, 8)}`
  const name = `E2E provisioning walkthrough ${Date.now()}`
  let deviceId: string | null = null

  try {
    await cleanupWalkthroughDevices(page)
    const enrollmentResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname.endsWith('/provisioning-enrollment'),
    )
    deviceId = await createProvisionedDevice(page, name, mac)
    const actualEnrollmentResponse = await enrollmentResponse

    expect(actualEnrollmentResponse.status()).toBe(200)
    const actualEnvelope = (await actualEnrollmentResponse.json()) as { data: EnrollmentState }
    expect(actualEnvelope.data.status).toBe('not_enrolled')
    expect(actualEnvelope.data.adapter_available).toBe(false)
    expect(actualEnvelope.data.can_enroll).toBe(false)
    expect(actualEnvelope.data.reason).toBeTruthy()
    expect(forbiddenEnrollmentKeys(actualEnvelope)).toEqual([])

    const enrollmentHeading = page.getByRole('heading', {
      name: 'Manufacturer provisioning enrollment',
    })
    await expect(enrollmentHeading).toBeVisible()
    await expect(
      page.getByText('provider credentials and access tokens are never stored here.'),
    ).toBeVisible()
    await expect(page.getByRole('button', { name: 'Enroll device' })).toBeDisabled()
    await enrollmentHeading.scrollIntoViewIfNeeded()
    await attachScreenshot(page, testInfo, 'device-enrollment-actual-state')

    let enrollmentMutations = 0
    await page.route('**/provisioning-enrollment', async (route) => {
      if (route.request().method() !== 'GET') {
        enrollmentMutations += 1
        await route.abort()

        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            status: 'not_enrolled',
            provider: 'isolated-test-provider',
            eligible: true,
            adapter_available: true,
            can_enroll: true,
            can_detach: false,
            reason: null,
            enrolled_at: null,
            detached_at: null,
          } satisfies EnrollmentState,
        }),
      })
    })

    await page.reload()
    await page.getByRole('button', { name: 'Enroll device' }).click()
    const confirmation = page.getByRole('dialog')
    await expect(confirmation.getByRole('heading', { name: 'Enroll this device?' })).toBeVisible()
    await expect(confirmation).toContainText('device identity and MAC address')
    await expect(confirmation.getByRole('button', { name: 'Cancel' })).toBeVisible()
    await expect(confirmation.getByRole('button', { name: 'Enroll device' })).toBeVisible()
    await page.waitForTimeout(300)
    await attachScreenshot(page, testInfo, 'device-enrollment-confirmation')
    await confirmation.getByRole('button', { name: 'Cancel' }).click()
    await expect(confirmation).toHaveCount(0)
    expect(enrollmentMutations).toBe(0)
    await page.unroute('**/provisioning-enrollment')

    const detailLineKeyPreview = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys/preview`),
    )
    await page.getByRole('button', { name: 'Line keys', exact: true }).click()
    expect((await detailLineKeyPreview).status()).toBe(200)
    const detailLineKeyPanel = page.getByRole('dialog')
    await expect(detailLineKeyPanel.getByRole('heading', { name: 'Line keys' })).toBeVisible()
    await expect(page.locator('h1')).toHaveText(name)
    await detailLineKeyPanel.getByRole('button', { name: 'Close', exact: true }).click()

    const lineKeyList = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        /\/api\/v1\/accounts\/[^/]+\/line-keys$/.test(new URL(response.url()).pathname),
    )
    await page.goto('/line-keys')
    const listResponse = await lineKeyList
    expect(listResponse.status()).toBe(200)
    const lineKeyDevices = (await listResponse.json()) as {
      data: Array<{ id: string; name: string | null }>
    }
    const deviceIndex = lineKeyDevices.data.findIndex((device) => device.id === deviceId)
    expect(deviceIndex).toBeGreaterThanOrEqual(0)

    const previewResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys/preview`),
    )
    await page.locator('tbody tr').nth(deviceIndex).click()
    const previewEnvelope = (await (await previewResponse).json()) as { data: LineKeyPreview }
    const preview = previewEnvelope.data

    expect(preview.capability.model).toMatchObject({
      matched: true,
      max_keys: 10,
      max_expansion_modules: 3,
      keys_per_expansion_module: 20,
    })
    expect(preview.value_choices.length).toBeGreaterThan(0)
    const presenceChoices = preview.value_choices.filter((choice) =>
      choice.types.includes('presence'),
    )
    const speedDialChoices = preview.value_choices.filter((choice) =>
      choice.types.includes('speed_dial'),
    )
    expect(presenceChoices.length).toBeGreaterThan(0)
    expect(
      presenceChoices.every(
        (choice) =>
          choice.value === choice.id &&
          /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
            choice.value,
          ),
      ),
    ).toBe(true)
    if (speedDialChoices.length > 0) {
      expect(
        speedDialChoices.every(
          (choice) => choice.value !== choice.id && choice.value === choice.description,
        ),
      ).toBe(true)
    }
    expect(JSON.stringify(preview)).not.toContain('switch_resource_id')

    const panel = page.getByRole('dialog')
    await expect(panel.getByRole('heading', { name: 'Line keys' })).toBeVisible()
    await expect(panel.getByRole('heading', { name: 'Main unit' })).toBeVisible()
    for (const number of [1, 2, 3]) {
      await expect(panel.getByRole('heading', { name: `Expansion ${number}` })).toBeVisible()
    }

    const mainUnit = panel.locator('section').filter({
      has: page.getByRole('heading', { name: 'Main unit' }),
    })
    await mainUnit.getByRole('button', { name: 'Add key' }).click()
    await mainUnit.getByRole('button', { name: 'Select type for position 0' }).click()
    await page.getByRole('option', { name: /Presence \/ BLF/ }).click()
    await expect(mainUnit.getByText('Extension', { exact: true })).toBeVisible()
    await mainUnit.getByRole('button', { name: 'Select value for position 0' }).click()

    const choices = page.getByRole('listbox')
    await expect(choices).toBeVisible()
    const [choicesBox, viewport] = await Promise.all([
      choices.boundingBox(),
      Promise.resolve(page.viewportSize()),
    ])
    expect(choicesBox).not.toBeNull()
    expect(viewport).not.toBeNull()
    expect(choicesBox!.x).toBeGreaterThanOrEqual(0)
    expect(choicesBox!.y).toBeGreaterThanOrEqual(0)
    expect(choicesBox!.x + choicesBox!.width).toBeLessThanOrEqual(viewport!.width)
    expect(choicesBox!.y + choicesBox!.height).toBeLessThanOrEqual(viewport!.height)
    await choices.getByRole('option').nth(1).click()
    await expect(choices).toHaveCount(0)
    await mainUnit.getByLabel('Label').fill('E2E monitored extension')

    const [panelOverflow, formOverflow, clippedControls] = await Promise.all([
      panel.evaluate((element) => element.scrollWidth - element.clientWidth),
      panel.locator('form').evaluate((element) => element.scrollWidth - element.clientWidth),
      panel.locator('button, input, textarea').evaluateAll((controls) =>
        controls
          .filter((control) => {
            const element = control as HTMLElement
            const style = window.getComputedStyle(element)

            return style.display !== 'none' && style.visibility !== 'hidden'
          })
          .map((control) => {
            const bounds = control.getBoundingClientRect()

            return {
              label: control.getAttribute('aria-label') ?? control.textContent?.trim() ?? '',
              left: bounds.left,
              right: bounds.right,
            }
          })
          .filter((bounds) => bounds.left < 0 || bounds.right > window.innerWidth + 1),
      ),
    ])

    expect(panelOverflow).toBeLessThanOrEqual(1)
    expect(formOverflow).toBeLessThanOrEqual(1)
    expect(clippedControls).toEqual([])
    await attachScreenshot(page, testInfo, 'line-keys-grouped-layout')

    const createLineKey = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys`),
    )
    await panel.getByRole('button', { name: 'Apply to device' }).click()
    expect((await createLineKey).status()).toBe(200)
    await expect(panel).toHaveCount(0)

    const editPreview = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys/preview`),
    )
    await page.locator('tbody tr').nth(deviceIndex).click()
    expect((await editPreview).status()).toBe(200)
    const editPanel = page.getByRole('dialog')
    await expect(editPanel.getByLabel('Label')).toHaveValue('E2E monitored extension')
    await editPanel.getByLabel('Label').fill('E2E monitored extension updated')

    const updateLineKey = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys`),
    )
    await editPanel.getByRole('button', { name: 'Apply to device' }).click()
    expect((await updateLineKey).status()).toBe(200)
    await expect(editPanel).toHaveCount(0)

    const clearPreview = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys/preview`),
    )
    await page.locator('tbody tr').nth(deviceIndex).click()
    expect((await clearPreview).status()).toBe(200)
    const clearPanel = page.getByRole('dialog')
    await clearPanel.getByRole('button', { name: 'Remove key' }).click()

    const clearLineKeys = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/devices/${deviceId}/line-keys`),
    )
    await clearPanel.getByRole('button', { name: 'Apply to device' }).click()
    expect((await clearLineKeys).status()).toBe(200)
    await expect(clearPanel).toHaveCount(0)

    expect(issues).toEqual([])
  } finally {
    await page.unroute('**/provisioning-enrollment').catch(() => undefined)

    if (deviceId) await deleteDevice(page, deviceId)
  }
})
