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
  await expect(starterDevice.getByRole('radio', { name: /VoIP phone/ })).toBeVisible()
  await expect(starterDevice.getByRole('radio', { name: /Cell phone/ })).toHaveCount(0)
  await expect(starterDevice.getByRole('radio', { name: /Landline/ })).toHaveCount(0)
  await expect(starterDevice.getByRole('radio', { name: /SIP URI/ })).toHaveCount(0)

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
