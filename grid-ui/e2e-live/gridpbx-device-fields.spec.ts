import { expect, test, type Page, type Response } from '@playwright/test'

type DeviceMutationBody = {
  data: {
    id: string
    configuration: {
      music_on_hold: { media_id: string | null; media_name: string | null }
      outbound_flags: { static: string[]; dynamic: string[] }
      sip: {
        custom_sip_headers: {
          in: Array<{ name: string; value: string }>
          out: Array<{ name: string; value: string }>
        }
      }
    }
  }
}

function silentWav(): Buffer {
  const sampleRate = 8_000
  const sampleCount = 800
  const dataLength = sampleCount * 2
  const wav = Buffer.alloc(44 + dataLength)

  wav.write('RIFF', 0)
  wav.writeUInt32LE(36 + dataLength, 4)
  wav.write('WAVE', 8)
  wav.write('fmt ', 12)
  wav.writeUInt32LE(16, 16)
  wav.writeUInt16LE(1, 20)
  wav.writeUInt16LE(1, 22)
  wav.writeUInt32LE(sampleRate, 24)
  wav.writeUInt32LE(sampleRate * 2, 28)
  wav.writeUInt16LE(2, 32)
  wav.writeUInt16LE(16, 34)
  wav.write('data', 36)
  wav.writeUInt32LE(dataLength, 40)

  return wav
}

function deviceMutation(page: Page, method: 'POST' | 'PUT'): Promise<Response> {
  return page.waitForResponse(
    (response) =>
      response.request().method() === method &&
      /\/api\/v1\/accounts\/[^/]+\/devices(?:\/[^/]+)?$/.test(new URL(response.url()).pathname),
  )
}

async function openAdvancedTab(page: Page, tab: 'SIP' | 'Audio' | 'Options'): Promise<void> {
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await page.getByRole('tab', { name: tab, exact: true }).click()
}

async function expectNoClientValidationErrors(page: Page): Promise<void> {
  await page.waitForTimeout(100)
  const errors = await page.locator('form .text-danger').allTextContents()
  const invalidControls = await page.locator('[aria-invalid="true"]').evaluateAll((controls) =>
    controls.map((control) => ({
      ariaLabel: control.getAttribute('aria-label'),
      placeholder: control.getAttribute('placeholder'),
      value:
        control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement
          ? control.value
          : control.textContent?.trim(),
    })),
  )

  expect({ errors, invalidControls }).toEqual({ errors: [], invalidControls: [] })
}

async function deleteDeviceFromDetail(page: Page): Promise<void> {
  const deletion = page.waitForResponse(
    (response) =>
      response.request().method() === 'DELETE' &&
      /\/api\/v1\/accounts\/[^/]+\/devices\/[^/]+$/.test(new URL(response.url()).pathname),
  )
  await page.getByRole('button', { name: 'Delete', exact: true }).click()
  await page.getByRole('button', { name: 'Delete device' }).click()
  expect((await deletion).status()).toBe(204)
  await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()
}

async function deleteDeviceById(page: Page, deviceId: string): Promise<void> {
  await page.goto(`/devices/${deviceId}`)
  await expect(page.getByRole('button', { name: 'Delete', exact: true })).toBeVisible()
  await deleteDeviceFromDetail(page)
}

async function deleteMediaByName(page: Page, mediaName: string): Promise<void> {
  await page.goto('/media')
  await expect(page.getByRole('heading', { name: 'Media & Music on Hold' })).toBeVisible()
  await page.getByPlaceholder('Search name, description, language…').fill(mediaName)
  await page.getByRole('button', { name: `View ${mediaName}` }).click()
  await page.getByRole('button', { name: 'Delete', exact: true }).click()
  await page.getByRole('textbox', { name: `Type ${mediaName} to confirm` }).fill(mediaName)
  const deletion = page.waitForResponse(
    (response) =>
      response.request().method() === 'DELETE' &&
      /\/api\/v1\/accounts\/[^/]+\/media\/[^/]+$/.test(new URL(response.url()).pathname),
  )
  await page.getByRole('button', { name: 'Delete media' }).click()
  expect((await deletion).status()).toBe(204)
  await expect(page.getByRole('button', { name: `View ${mediaName}` })).toHaveCount(0)
}

async function cleanupExistingDeviceFieldFixtures(page: Page): Promise<void> {
  await page.goto('/devices')
  await expect(page.getByText('Loading projected devices…')).toHaveCount(0)
  const search = 'E2E advanced device'
  const results = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      response.request().method() === 'GET' &&
      /\/api\/v1\/accounts\/[^/]+\/devices$/.test(url.pathname) &&
      url.searchParams.get('search') === search
    )
  })
  await page.getByPlaceholder('Search name, model, MAC, extension…').fill(search)
  const devices = ((await (await results).json()).data ?? []) as Array<{ id: string }>

  for (const device of devices) {
    await deleteDeviceById(page, device.id)
  }

  await page.goto('/media')
  await expect(page.getByText('Loading projected media…')).toHaveCount(0)
  await page.getByPlaceholder('Search name, description, language…').fill('E2E device hold')
  const mediaResults = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      response.request().method() === 'GET' &&
      /\/api\/v1\/accounts\/[^/]+\/media$/.test(url.pathname) &&
      url.searchParams.get('search') === 'E2E device hold'
    )
  })
  const media = ((await (await mediaResults).json()).data ?? []) as Array<{ name: string }>

  for (const item of media) {
    await deleteMediaByName(page, item.name)
  }
}

test('creates, edits, and clears Device music on hold', async ({ page }) => {
  const suffix = Date.now().toString()
  const mediaName = `E2E device hold ${suffix}`
  const deviceName = `E2E advanced device ${suffix}`
  let deviceId: string | null = null
  let mediaCreated = false

  try {
    await cleanupExistingDeviceFieldFixtures(page)
    await page.goto('/media')
    await expect(page.getByRole('heading', { name: 'Media & Music on Hold' })).toBeVisible()
    await page.getByRole('button', { name: 'Upload media' }).click()
    await page.getByLabel('Media name', { exact: true }).fill(mediaName)
    await page.getByLabel('Audio file').setInputFiles({
      name: 'silence.wav',
      mimeType: 'audio/wav',
      buffer: silentWav(),
    })

    const mediaCreate = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/media$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Upload media' }).last().click()
    const mediaResponse = await mediaCreate
    expect(mediaResponse.status()).toBe(201)
    const mediaId = (await mediaResponse.json()).data.id as string
    mediaCreated = true

    await page.goto('/devices')
    await expect(page.getByRole('heading', { name: 'Devices' })).toBeVisible()
    await page.getByRole('link', { name: 'Add device' }).click()
    await page.getByLabel('Device name').fill(deviceName)

    await openAdvancedTab(page, 'SIP')
    await page.getByLabel('SIP username').fill(`e2e${suffix.slice(-10)}`)
    await page.getByLabel('SIP password').fill('E2E-device-pass-123')
    await page.getByRole('tab', { name: 'Audio', exact: true }).click()
    await page.getByRole('button', { name: 'Select device music on hold' }).click()
    await page.getByRole('option', { name: mediaName, exact: true }).click()

    const createDevice = deviceMutation(page, 'POST')
    await page.getByRole('button', { name: 'Create device' }).click()
    await expectNoClientValidationErrors(page)
    const createResponse = await createDevice
    expect(createResponse.status()).toBe(201)
    const created = (await createResponse.json()) as DeviceMutationBody
    deviceId = created.data.id
    expect(created.data.configuration.music_on_hold).toEqual({
      media_id: mediaId,
      media_name: mediaName,
    })

    await expect(page.getByRole('heading', { name: deviceName, level: 1 })).toBeVisible()
    await page.getByRole('link', { name: 'Edit' }).click()
    await expect(page.getByRole('heading', { name: 'Edit device' })).toBeVisible()

    await openAdvancedTab(page, 'Audio')
    await expect(page.getByRole('button', { name: 'Select device music on hold' })).toContainText(
      mediaName,
    )

    const updateDevice = deviceMutation(page, 'PUT')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expectNoClientValidationErrors(page)
    const updateResponse = await updateDevice
    expect(updateResponse.status()).toBe(200)
    const updated = (await updateResponse.json()) as DeviceMutationBody
    expect(updated.data.configuration.music_on_hold.media_id).toBe(mediaId)

    await expect(page.getByRole('heading', { name: deviceName, level: 1 })).toBeVisible()
    await page.getByRole('link', { name: 'Edit' }).click()
    await openAdvancedTab(page, 'Audio')
    await page.getByRole('button', { name: 'Select device music on hold' }).click()
    await page.getByRole('option', { name: 'Inherit account music', exact: true }).click()

    const clearDevice = deviceMutation(page, 'PUT')
    await page.getByRole('button', { name: 'Save changes' }).click()
    await expectNoClientValidationErrors(page)
    const clearResponse = await clearDevice
    expect(clearResponse.status()).toBe(200)
    const cleared = (await clearResponse.json()) as DeviceMutationBody
    expect(cleared.data.configuration.music_on_hold).toEqual({
      media_id: null,
      media_name: null,
    })

    await expect(page.getByRole('heading', { name: deviceName, level: 1 })).toBeVisible()
    await deleteDeviceFromDetail(page)
    deviceId = null
    await deleteMediaByName(page, mediaName)
    mediaCreated = false
  } finally {
    if (deviceId) {
      await deleteDeviceById(page, deviceId).catch(() => undefined)
    }

    if (mediaCreated) {
      await deleteMediaByName(page, mediaName).catch(() => undefined)
    }
  }
})
