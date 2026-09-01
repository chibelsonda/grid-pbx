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

test('shows the selected account reseller boundary without mutation controls', async ({ page }) => {
  const issues = collectPageIssues(page)

  const [hierarchyResponse, resellerResponse] = await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/hierarchy')),
    page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        response.url().includes('/api/v1/accounts/') &&
        response.url().endsWith('/reseller'),
    ),
    page.goto('/reseller'),
  ])

  expect(hierarchyResponse.ok()).toBe(true)
  expect(resellerResponse.ok()).toBe(true)
  const resellerStatus = (await resellerResponse.json()) as {
    data: {
      administration: Record<string, boolean>
    }
  }
  const expectedAdministrationCapabilities = [
    'account_creation_available',
    'account_move_available',
    'account_deletion_available',
    'limit_mutations_available',
    'service_plan_mutations_available',
    'service_override_mutations_available',
    'top_up_available',
    'switch_service_synchronization_available',
    'switch_service_reconciliation_available',
  ]

  expect(Object.keys(resellerStatus.data.administration).sort()).toEqual(
    expectedAdministrationCapabilities.sort(),
  )
  expect(Object.values(resellerStatus.data.administration)).toEqual(Array(9).fill(false))
  expect(JSON.stringify(resellerStatus.data.administration)).not.toMatch(
    /accept_charges|service_plan_ids|switch_account_id/,
  )
  await expect(page.getByRole('heading', { name: 'Reseller administration' })).toBeVisible()
  await expect(page.getByText('Switch account role')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Billing ownership' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Hierarchy service totals' })).toBeVisible()
  const quantityGroups = page.getByTestId('service-quantity-group')
  if ((await quantityGroups.count()) > 0) {
    await quantityGroups.first().click()
    await expect(page.getByTestId('service-quantity-item').first()).toBeVisible()

    const quantityFilter = page.getByRole('searchbox', { name: 'Filter projected quantities' })
    await quantityFilter.fill('no-such-projected-quantity')
    await expect(page.getByText('No projected quantities match')).toBeVisible()
    await quantityFilter.clear()
    await expect(quantityGroups.first()).toBeVisible()
  }
  await expect(
    page.getByRole('heading', { name: 'Protected administration boundary' }),
  ).toBeVisible()
  await expect(page.getByTestId('reseller-mutation-preflight')).toContainText(
    /Dependencies (ready|blocked)/,
  )
  await expect(page.getByTestId('reseller-mutation-preflight')).toContainText(
    'Platform Policy Available',
  )
  await page.getByRole('button', { name: /Platform Policy Available/ }).click()
  await expect(
    page.getByTestId('reseller-mutation-preflight').getByText('Recovery guidance'),
  ).toBeVisible()
  await expect(page.getByRole('button', { name: 'Promote account', exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Demote account', exact: true })).toHaveCount(0)
  const administrationCapabilities = page.getByTestId('account-administration-capabilities')
  await expect(administrationCapabilities).toBeVisible()
  await expect(administrationCapabilities).toContainText('Lifecycle and billing operations')
  await expect(administrationCapabilities.getByText('Unavailable', { exact: true })).toHaveCount(9)
  await expect(page.getByRole('button', { name: /create account/i })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /move account/i })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /delete account/i })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /change limits/i })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /change service plans/i })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /top up/i })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /reconcile/i })).toHaveCount(0)

  const currentAccount = page.getByRole('button', { name: 'Current account' })
  const originalAccountName = (await currentAccount.textContent())?.trim() ?? ''
  await currentAccount.click()
  const accountOptions = page.getByRole('option')
  const accountOptionCount = await accountOptions.count()
  let descendantAccountName: string | null = null

  for (let index = 0; index < accountOptionCount; index += 1) {
    const optionName = (await accountOptions.nth(index).textContent())?.trim() ?? ''

    if (optionName && optionName !== originalAccountName) {
      descendantAccountName = optionName
      break
    }
  }

  if (descendantAccountName) {
    const [childHierarchyResponse, childResellerResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/hierarchy')),
      page.waitForResponse(
        (response) =>
          response.request().method() === 'GET' &&
          response.url().includes('/api/v1/accounts/') &&
          response.url().endsWith('/reseller'),
      ),
      page.getByRole('option', { name: descendantAccountName, exact: true }).click(),
    ])
    const childReseller = (await childResellerResponse.json()) as {
      data: {
        billing_reseller: { name: string } | null
        billing_reseller_projected: boolean | null
        service_projection_last_synced_at: string | null
      }
    }

    expect(childHierarchyResponse.ok()).toBe(true)
    expect(childResellerResponse.ok()).toBe(true)
    expect(childReseller.data.billing_reseller?.name).toBe(originalAccountName)
    expect(childReseller.data.billing_reseller_projected).toBe(true)
    expect(childReseller.data.service_projection_last_synced_at).not.toBeNull()
    await expect(page.getByText('Projected in GridPBX')).toBeVisible()

    const [restoredHierarchyResponse, restoredResellerResponse] = await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/hierarchy')),
      page.waitForResponse(
        (response) =>
          response.request().method() === 'GET' &&
          response.url().includes('/api/v1/accounts/') &&
          response.url().endsWith('/reseller'),
      ),
      currentAccount
        .click()
        .then(() => page.getByRole('option', { name: originalAccountName, exact: true }).click()),
    ])

    expect(restoredHierarchyResponse.ok()).toBe(true)
    expect(restoredResellerResponse.ok()).toBe(true)

    await expect(page.getByRole('heading', { name: 'Descendant service ownership' })).toBeVisible()
    const syncButton = page.getByRole('button', {
      name: `Synchronize services for ${descendantAccountName}`,
    })
    const descendantRow = syncButton.locator('xpath=ancestor::article')
    const syncStartedResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'POST' && response.url().endsWith('/sync/services'),
    )
    await syncButton.click()
    const syncStartedResponse = await syncStartedResponsePromise

    expect(syncStartedResponse.status()).toBe(202)
    await expect(syncButton).toHaveText('Sync', { timeout: 30_000 })
    await expect(descendantRow.getByText('Healthy', { exact: true })).toBeVisible()
  } else {
    await page.keyboard.press('Escape')
  }

  const reviewDescendants = page.getByRole('button', { name: 'Review descendants' })
  if (await reviewDescendants.isVisible()) {
    const candidatesResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' && response.url().endsWith('/descendant-onboarding'),
    )
    await reviewDescendants.click()
    const candidatesResponse = await candidatesResponsePromise

    expect(candidatesResponse.ok()).toBe(true)
    const candidates = (await candidatesResponse.json()) as {
      data: { candidates: Array<Record<string, unknown>> }
    }
    for (const candidate of candidates.data.candidates) {
      expect(candidate).not.toHaveProperty('switch_account_id')
      expect(candidate).not.toHaveProperty('account_id')
    }
    await expect(page.getByRole('heading', { name: 'Onboard a descendant' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Target organization' })).toBeVisible()
    await page.getByRole('button', { name: 'Close panel' }).click()
  }

  const horizontalOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )

  expect(horizontalOverflow).toBeLessThanOrEqual(1)
  expect(issues).toEqual([])
})
