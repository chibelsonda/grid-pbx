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
  const uiOrigin = new URL(page.url()).origin
  const response = await page.request.delete(url, {
    headers: {
      Accept: 'application/json',
      Origin: uiOrigin,
      Referer: page.url(),
      ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.value) } : {}),
    },
  })

  return response.status()
}

test('shows schema-aware Temporal fields, bounded listboxes, and inline validation', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.goto('/business-hours')
  await expect(page.getByRole('heading', { name: 'Business Hours & Schedules' })).toBeVisible()
  await page.getByRole('button', { name: 'New rule' }).click()
  await expect(page.getByRole('heading', { name: 'Create temporal rule' })).toBeVisible()

  await expectControlRowAligned(
    page.getByRole('button', { name: 'Cycle' }),
    page.getByLabel('Every'),
  )

  await page.getByRole('button', { name: 'Cycle' }).click()
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
  await page.getByRole('option', { name: 'Monthly', exact: true }).click()
  await expect(page.getByLabel('Days of month')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Ordinal' })).toBeVisible()

  await page.getByLabel('Days of month').fill('1, weekday, 32')
  await page.getByRole('button', { name: 'Save rule' }).click()
  const name = page.getByLabel('Name', { exact: true })
  const days = page.getByLabel('Days of month')
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(name).toHaveClass(/border-red-400/)
  await expect(days).toHaveAttribute('aria-invalid', 'true')
  await expect(days).toHaveClass(/border-red-400/)
  await expect(page.getByText('Enter a rule name.')).toBeVisible()
  await expect(page.getByText('Check the highlighted fields and try again.')).toHaveCount(0)

  await page.getByRole('button', { name: 'Cycle' }).click()
  await page.getByRole('option', { name: 'Specific date' }).click()
  await expect(page.getByLabel('Days of month')).toHaveCount(0)
  await expect(page.getByText('Weekdays', { exact: true })).toHaveCount(0)
  expect(issues).toEqual([])
})

test('creates, edits, controls, orders, and removes Temporal routing resources', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const suffix = String(Date.now()).slice(-8)
  const ruleName = `E2E hours ${suffix}`
  const updatedRuleName = `${ruleName} updated`
  const setName = `E2E schedule ${suffix}`
  const updatedSetName = `${setName} updated`
  let apiOrigin: string | null = null
  let accountId: string | null = null
  let ruleId: string | null = null
  let setId: string | null = null

  try {
    await page.goto('/business-hours')
    await page.getByRole('button', { name: 'New rule' }).click()
    await page.getByLabel('Name', { exact: true }).fill(ruleName)
    const creation = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/temporal-rules$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save rule' }).click()
    const createResponse = await creation
    expect(createResponse.status()).toBe(201)
    apiOrigin = new URL(createResponse.url()).origin
    accountId = new URL(createResponse.url()).pathname.match(/\/accounts\/([^/]+)/)?.[1] ?? null
    const created = (await createResponse.json()) as {
      data: { id: string; enabled: boolean | null }
    }
    ruleId = created.data.id
    expect(created.data.enabled).toBeNull()

    await page.getByText(ruleName, { exact: true }).click()
    const rulePanel = page.getByRole('dialog', { name: 'Edit temporal rule' })
    const forceInactive = rulePanel.getByRole('button', { name: 'Force inactive' })
    await expect(forceInactive).toBeEnabled()
    await forceInactive.click()
    const forceInactiveDialog = page.getByRole('dialog', { name: 'Force inactive' })
    const confirmForceInactive = forceInactiveDialog.getByRole('button', {
      name: 'Force inactive',
    })
    await expect(confirmForceInactive).toBeVisible()
    const disabling = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        new URL(response.url()).pathname.endsWith(`/temporal-rules/${ruleId}/commands`),
    )
    await confirmForceInactive.click()
    const disableResponse = await disabling
    expect(disableResponse.status()).toBe(200)
    expect(((await disableResponse.json()) as { data: { enabled: boolean } }).data.enabled).toBe(
      false,
    )
    await expect(page.getByText('Forced inactive', { exact: true })).toBeVisible()

    await expect(page.getByRole('button', { name: 'Save rule' })).toBeEnabled()
    const editableName = page.getByLabel('Name', { exact: true })
    await editableName.fill(updatedRuleName)
    await expect(editableName).toHaveValue(updatedRuleName)
    const update = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/temporal-rules/${ruleId}`),
    )
    await page.getByRole('button', { name: 'Save rule' }).click()
    const updateResponse = await update
    expect(updateResponse.status()).toBe(200)
    expect(updateResponse.request().postDataJSON()).toMatchObject({ name: updatedRuleName })
    expect(
      ((await updateResponse.json()) as { data: { name: string; enabled: boolean } }).data,
    ).toMatchObject({ name: updatedRuleName, enabled: false })

    await page.getByText(updatedRuleName, { exact: true }).click()
    const resumeRule = page
      .getByRole('dialog', { name: 'Edit temporal rule' })
      .getByRole('button', { name: 'Resume schedule' })
    await expect(resumeRule).toBeEnabled()
    await resumeRule.click()
    const resumeRuleDialog = page.getByRole('dialog', { name: 'Resume schedule' })
    const confirmResumeRule = resumeRuleDialog.getByRole('button', { name: 'Resume schedule' })
    await expect(confirmResumeRule).toBeVisible()
    const resetting = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        new URL(response.url()).pathname.endsWith(`/temporal-rules/${ruleId}/commands`),
    )
    await confirmResumeRule.click()
    const resetResponse = await resetting
    expect(resetResponse.status()).toBe(200)
    expect(((await resetResponse.json()) as { data: { enabled: null } }).data.enabled).toBeNull()
    await expect(page.getByText('Following schedule', { exact: true })).toBeVisible()
    await page
      .getByRole('dialog', { name: 'Edit temporal rule' })
      .getByRole('button', { name: 'Cancel' })
      .click()
    await expect(page.getByRole('heading', { name: 'Edit temporal rule' })).toHaveCount(0)

    await page.getByRole('tab', { name: 'Rule Sets' }).click()
    await page.getByRole('button', { name: 'New rule set' }).click()
    await page.getByLabel('Name', { exact: true }).fill(setName)
    await page.getByText(updatedRuleName, { exact: true }).click()
    await expect(page.getByText('Evaluation order')).toBeVisible()
    const setCreation = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        /\/api\/v1\/accounts\/[^/]+\/temporal-rule-sets$/.test(new URL(response.url()).pathname),
    )
    await page.getByRole('button', { name: 'Save rule set' }).click()
    const setCreateResponse = await setCreation
    expect(setCreateResponse.status()).toBe(201)
    const createdSet = (await setCreateResponse.json()) as {
      data: { id: string; rules: Array<{ rule: { id: string } | null; position: number }> }
    }
    setId = createdSet.data.id
    expect(createdSet.data.rules).toEqual([
      expect.objectContaining({ rule: expect.objectContaining({ id: ruleId }), position: 0 }),
    ])

    await page.getByText(setName, { exact: true }).click()
    let setPanel = page.getByRole('dialog', { name: 'Edit rule set' })
    await setPanel.getByLabel('Name', { exact: true }).fill(updatedSetName)
    const setUpdate = page.waitForResponse(
      (response) =>
        response.request().method() === 'PUT' &&
        new URL(response.url()).pathname.endsWith(`/temporal-rule-sets/${setId}`),
    )
    await setPanel.getByRole('button', { name: 'Save rule set' }).click()
    const setUpdateResponse = await setUpdate
    expect(setUpdateResponse.status()).toBe(200)
    expect(setUpdateResponse.request().postDataJSON()).toMatchObject({
      name: updatedSetName,
      rule_ids: [ruleId],
    })

    await page.getByText(updatedSetName, { exact: true }).click()
    setPanel = page.getByRole('dialog', { name: 'Edit rule set' })
    await expect(setPanel.getByLabel('Name', { exact: true })).toHaveValue(updatedSetName)
    const forceActive = setPanel.getByRole('button', { name: 'Force active' })
    await expect(forceActive).toBeEnabled()
    await forceActive.click()
    const forceActiveDialog = page.getByRole('dialog', { name: 'Force active' })
    const confirmForceActive = forceActiveDialog.getByRole('button', { name: 'Force active' })
    await expect(confirmForceActive).toBeVisible()
    const enablingSet = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        new URL(response.url()).pathname.endsWith(`/temporal-rule-sets/${setId}/commands`),
    )
    await confirmForceActive.click()
    expect((await enablingSet).status()).toBe(200)
    await expect(page.getByText('Forced active', { exact: true })).toBeVisible()

    const resumeSet = page
      .getByRole('dialog', { name: 'Edit rule set' })
      .getByRole('button', { name: 'Resume schedule' })
    await expect(resumeSet).toBeEnabled()
    await resumeSet.click()
    const resumeSetDialog = page.getByRole('dialog', { name: 'Resume schedule' })
    const confirmResumeSet = resumeSetDialog.getByRole('button', { name: 'Resume schedule' })
    await expect(confirmResumeSet).toBeVisible()
    const resettingSet = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' &&
        new URL(response.url()).pathname.endsWith(`/temporal-rule-sets/${setId}/commands`),
    )
    await confirmResumeSet.click()
    expect((await resettingSet).status()).toBe(200)
  } finally {
    if (apiOrigin !== null && accountId !== null && setId !== null) {
      const status = await deleteApiResource(
        page,
        `${apiOrigin}/api/v1/accounts/${accountId}/temporal-rule-sets/${setId}`,
      )
      expect(status).toBe(204)
    }

    if (apiOrigin !== null && accountId !== null && ruleId !== null) {
      const status = await deleteApiResource(
        page,
        `${apiOrigin}/api/v1/accounts/${accountId}/temporal-rules/${ruleId}`,
      )
      expect(status).toBe(204)
    }
  }

  expect(issues).toEqual([])
})
