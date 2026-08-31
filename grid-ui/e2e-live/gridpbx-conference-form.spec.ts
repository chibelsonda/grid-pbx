import { existsSync } from 'node:fs'
import process from 'node:process'

import { expect, test, type Page } from '@playwright/test'
import { expectControlRowAligned } from './support/formAlignment.js'

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

async function deleteApiResource(page: Page, url: string): Promise<number> {
  const xsrfCookie = (await page.context().cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN')
  const response = await page.request.delete(url, {
    headers: {
      Accept: 'application/json',
      Origin: new URL(page.url()).origin,
      Referer: page.url(),
      ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.value) } : {}),
    },
    failOnStatusCode: false,
  })

  return response.status()
}

test('shows schema-backed Conference sounds with inline validation and bounded listboxes', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/conferences')
  await expect(page.getByRole('heading', { name: 'Conferences' })).toBeVisible()
  await page.getByRole('button', { name: 'New conference' }).click()
  await expect(page.getByRole('heading', { name: 'Create conference' })).toBeVisible()

  await expect(page.getByRole('tablist', { name: 'Form sections' }).getByRole('tab')).toHaveText([
    'Basic',
    'Advanced',
  ])
  await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
  await expect(
    page.getByRole('tablist', { name: 'Conference advanced sections' }).getByRole('tab'),
  ).toHaveText(['Basic', 'Options', 'Conference Server'])
  await page.getByRole('tab', { name: 'Options', exact: true }).click()
  await expectControlRowAligned(
    page.getByRole('button', { name: 'Participant entry tone' }),
    page.getByRole('button', { name: 'Participant exit tone' }),
  )
  await page.getByRole('button', { name: 'Participant entry tone' }).click()
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
  await page.getByRole('option', { name: 'Play selected media' }).click()
  await expect(page.getByRole('button', { name: 'Entry tone media' })).toBeVisible()

  await page
    .getByRole('tablist', { name: 'Form sections' })
    .getByRole('tab', { name: 'Basic' })
    .click()
  await page.getByLabel('Member numbers').fill('not-a-number')
  await page.getByRole('button', { name: 'Save conference' }).click()
  const name = page.getByLabel('Name', { exact: true })
  const memberNumbers = page.getByLabel('Member numbers')
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(memberNumbers).toHaveAttribute('aria-invalid', 'true')
  await expect(memberNumbers).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a conference name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)
  expect(issues).toEqual([])
})

test('round-trips Conference access, advanced profiles, and tone configuration', async ({
  page,
}) => {
  test.setTimeout(180_000)
  const issues = collectPageIssues(page)
  const suffix = String(Date.now()).slice(-7)
  const name = `E2E conference ${suffix}`
  let createdId: string | null = null
  let cleanupUrl: string | null = null

  try {
    await page.goto('/conferences')
    await page.getByRole('button', { name: 'New conference' }).click()
    await page.getByLabel('Name', { exact: true }).fill(name)
    await page.getByLabel('Member numbers').fill(`7${suffix}`)
    await page.getByRole('textbox', { name: 'Member PINs', exact: true }).fill('1234, 5678')
    const owner = page.getByLabel('Owner')
    await owner.click()
    const ownerOptions = page.getByRole('option')
    expect(await ownerOptions.count()).toBeGreaterThan(1)
    const ownerLabel = (await ownerOptions.nth(1).innerText()).trim()
    await ownerOptions.nth(1).click()
    await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
    await page.getByRole('textbox', { name: 'Moderator PINs', exact: true }).fill('9876 8765')
    await page.getByRole('tab', { name: 'Conference Server', exact: true }).click()
    await page.getByLabel('General conference numbers').fill(`8${suffix}`)
    await page.getByLabel('Profile name').fill('default')
    await page.getByLabel('Caller controls').fill('default')
    await page.getByLabel('Moderator controls').fill('default')
    await page.getByRole('tab', { name: 'Options', exact: true }).click()
    await page.getByRole('button', { name: 'Participant entry tone' }).click()
    await page.getByRole('option', { name: 'Do not play a tone' }).click()

    const creation = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/conferences$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save conference' }).click()
    const createResponse = await creation
    expect(createResponse.status()).toBe(201)
    const createRequest = createResponse.request().postDataJSON() as { owner_id: string }
    const ownerPublicId = createRequest.owner_id
    expect(ownerPublicId).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    )
    expect(createRequest).toMatchObject({
      member_pins: ['1234', '5678'],
      moderator_pins: ['9876', '8765'],
      profile_name: 'default',
      caller_controls: 'default',
      moderator_controls: 'default',
    })
    const created = (await createResponse.json()) as {
      data: {
        id: string
        owner: { id: string } | null
        member_pin_configured: boolean
        moderator_pin_configured: boolean
        profile_name: string | null
        caller_controls: string | null
        moderator_controls: string | null
        entry_tone: { mode: string }
        exit_tone: { mode: string }
      }
    }
    createdId = created.data.id
    cleanupUrl = `${createResponse.url()}/${createdId}`
    expect(created.data.owner?.id).toBe(ownerPublicId)
    expect(created.data.member_pin_configured).toBe(true)
    expect(created.data.moderator_pin_configured).toBe(true)
    expect(created.data.profile_name).toBe('default')
    expect(created.data.caller_controls).toBe('default')
    expect(created.data.moderator_controls).toBe('default')
    expect(created.data.entry_tone.mode).toBe('disabled')
    expect(created.data.exit_tone.mode).toBe('enabled')
    expect(JSON.stringify(created)).not.toContain('1234')
    expect(JSON.stringify(created)).not.toContain('5678')
    expect(JSON.stringify(created)).not.toContain('9876')
    expect(JSON.stringify(created)).not.toContain('8765')
    expect(JSON.stringify(created)).not.toContain('switch_resource_id')
    const rawOwnerId = process.env.GRID_E2E_CONFERENCE_RAW_OWNER_ID?.trim()
    const privateMarker = process.env.GRID_E2E_CONFERENCE_PRIVATE_MARKER?.trim()
    if (rawOwnerId) expect(JSON.stringify(created)).not.toContain(rawOwnerId)
    if (privateMarker) expect(JSON.stringify(created)).not.toContain(privateMarker)
    const injectionFile = process.env.GRID_E2E_CONFERENCE_INJECTION_FILE?.trim()
    if (injectionFile) {
      await expect.poll(() => existsSync(injectionFile), { timeout: 120_000 }).toBe(true)
    }

    await expect(page.getByRole('heading', { name: 'Create conference' })).toHaveCount(0)
    await page.getByText(name, { exact: true }).click()
    const memberPins = page.getByRole('textbox', { name: 'Member PINs', exact: true })
    await expect(memberPins).toHaveValue('')
    await expect(memberPins).toHaveAttribute('placeholder', 'Configured — enter PINs to replace')
    await memberPins.fill('2468, 1357')
    await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
    await page.getByRole('tab', { name: 'Options', exact: true }).click()
    await page.getByRole('switch', { name: 'Join deaf', exact: true }).click()
    await page.getByRole('button', { name: 'Participant entry tone' }).click()
    await page.getByRole('option', { name: 'Play the standard tone' }).click()
    const update = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/conferences/${createdId}`),
    )
    await page.getByRole('button', { name: 'Save conference' }).click()
    const updateResponse = await update
    expect(updateResponse.status()).toBe(200)
    expect(updateResponse.request().postDataJSON()).toMatchObject({
      owner_id: ownerPublicId,
      member_pins: ['2468', '1357'],
      moderator_pins: [],
      profile_name: 'default',
      caller_controls: 'default',
      moderator_controls: 'default',
      member_join_deaf: true,
    })
    const updated = (await updateResponse.json()) as {
      data: {
        member_pin_configured: boolean
        moderator_pin_configured: boolean
        member_join_deaf: boolean
        entry_tone: { mode: string }
      }
    }
    expect(updated.data.member_pin_configured).toBe(true)
    expect(updated.data.moderator_pin_configured).toBe(true)
    expect(updated.data.member_join_deaf).toBe(true)
    expect(updated.data.entry_tone.mode).toBe('enabled')
    expect(JSON.stringify(updated)).not.toContain('2468')
    expect(JSON.stringify(updated)).not.toContain('1357')
    if (rawOwnerId) expect(JSON.stringify(updated)).not.toContain(rawOwnerId)
    if (privateMarker) expect(JSON.stringify(updated)).not.toContain(privateMarker)

    await page.getByText(name, { exact: true }).click()
    await expect(page.getByLabel('Owner')).toContainText(ownerLabel)
    await page.getByRole('tab', { name: 'Advanced', exact: true }).click()
    await page.getByRole('tab', { name: 'Options', exact: true }).click()
    await expect(page.getByRole('switch', { name: 'Join deaf', exact: true })).toBeChecked()
    await page.getByRole('tab', { name: 'Conference Server', exact: true }).click()
    await expect(page.getByLabel('Profile name')).toHaveValue('default')
    await expect(page.getByLabel('Caller controls')).toHaveValue('default')
    await expect(page.getByLabel('Moderator controls')).toHaveValue('default')

    const verificationFile = process.env.GRID_E2E_CONFERENCE_VERIFICATION_FILE?.trim()
    if (verificationFile) {
      await expect.poll(() => existsSync(verificationFile), { timeout: 120_000 }).toBe(true)
    }
    await page.getByRole('button', { name: 'Cancel' }).click()
  } finally {
    if (createdId !== null) {
      await page.goto('/conferences')
      const row = page.getByText(name, { exact: true })

      if (await row.isVisible()) {
        await row.click()
        const deletion = page.waitForResponse(
          (response) =>
            response.request().method() === 'DELETE' &&
            new URL(response.url()).pathname.endsWith(`/conferences/${createdId}`),
        )
        await page.getByRole('button', { name: 'Delete conference' }).click()
        await page.getByRole('dialog').getByRole('button', { name: 'Delete conference' }).click()
        expect((await deletion).status()).toBe(204)
        cleanupUrl = null
      }
    }

    if (cleanupUrl !== null) expect(await deleteApiResource(page, cleanupUrl)).toBe(204)
  }

  expect(issues).toEqual([])
})
