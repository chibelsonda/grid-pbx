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

test('shows the safe account projection and explicit settings boundaries', async ({ page }) => {
  const issues = collectPageIssues(page)
  let updatePayload: Record<string, unknown> | null = null
  let statusPayload: Record<string, unknown> | null = null
  const detail = (name = 'Grid Support', enabled = true) => ({
    id: 'account-public-id',
    name,
    realm: 'support.example.test',
    timezone: 'Asia/Manila',
    enabled,
    organization: { id: 'organization-public-id', name: 'GridPBX' },
    resource_counts: {
      extensions: 3,
      devices: 4,
      phone_numbers: 2,
      callflows: 5,
      voicemail_boxes: 3,
      queues: 1,
      media: 6,
      recordings: 7,
    },
    configuration: {
      organization_name: 'Grid Corp',
      language: 'en-US',
      call_waiting_enabled: true,
      do_not_disturb_enabled: false,
      outbound_privacy: 'none',
      show_rate: false,
      ringtone_internal: null,
      ringtone_external: null,
      caller_id: {
        internal: { name: 'Support', number: '1000' },
        external: {
          name: 'Grid Support',
          phone_number_id: '10000000-0000-4000-8000-000000000001',
          number: '+15550001000',
          unresolved: false,
        },
        emergency: {
          name: 'Grid Emergency',
          phone_number_id: '10000000-0000-4000-8000-000000000002',
          number: '+15550001911',
          unresolved: false,
        },
      },
    },
    options: {
      caller_id_numbers: [
        {
          id: '10000000-0000-4000-8000-000000000001',
          number: '+15550001000',
          display_name: 'Grid Support',
          e911_enabled: false,
        },
        {
          id: '10000000-0000-4000-8000-000000000002',
          number: '+15550001911',
          display_name: 'Grid Emergency',
          e911_enabled: true,
        },
      ],
    },
    projection: { status: 'synced', version: 1, last_synced_at: '2026-08-29T00:00:00Z' },
    permissions: { can_manage_settings: true },
    configuration_boundaries: {
      identity_defaults: 'safe_fields_available',
      calling_defaults: 'safe_fields_available',
      advanced_routing: 'planned',
      enable_disable: 'implemented_confirmed',
      billing_topup: 'provider_required',
    },
  })
  await page.route('**/api/v1/accounts/*/status', (route) => {
    statusPayload = route.request().postDataJSON() as Record<string, unknown>
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: detail('Grid Operations', false) }),
    })
  })
  await page.route('**/api/v1/accounts/*', (route) => {
    if (!/^\/api\/v1\/accounts\/[^/]+$/.test(new URL(route.request().url()).pathname)) {
      return route.fallback()
    }

    if (route.request().method() === 'PUT') {
      updatePayload = route.request().postDataJSON() as Record<string, unknown>
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: detail(route.request().method() === 'PUT' ? 'Grid Operations' : undefined),
      }),
    })
  })

  await page.goto('/accounts')
  await expect(page.getByRole('heading', { name: 'Accounts', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Grid Support' })).toBeVisible()
  await expect(page.getByText('support.example.test')).toBeVisible()
  await expect(page.getByText('Asia/Manila')).toBeVisible()
  await expect(page.getByText('Projected resources')).toBeVisible()
  await expect(page.getByText('Operational and billing controls')).toBeVisible()
  await page.getByRole('button', { name: 'Edit settings' }).click()
  const name = page.getByRole('textbox', { name: 'Account name' })
  await name.fill('')
  await page.getByRole('button', { name: 'Save settings' }).click()
  await expect(name).toHaveAttribute('aria-invalid', 'true')
  await expect(page.getByText('Enter an account name.')).toBeVisible()
  await name.fill('Grid Operations')
  await page.getByRole('button', { name: 'Save settings' }).click()
  await expect(page.getByRole('heading', { name: 'Grid Operations' })).toBeVisible()
  expect(updatePayload).toMatchObject({
    name: 'Grid Operations',
    timezone: 'Asia/Manila',
    outbound_privacy: 'none',
    caller_id: {
      external: {
        phone_number_id: '10000000-0000-4000-8000-000000000001',
      },
      emergency: {
        phone_number_id: '10000000-0000-4000-8000-000000000002',
      },
    },
  })
  await page.getByRole('button', { name: 'Disable account' }).click()
  const confirmation = page.getByRole('textbox', { name: 'Confirmation text' })
  await confirmation.fill('wrong')
  await expect(confirmation).toHaveAttribute('aria-invalid', 'true')
  await confirmation.fill('Grid Operations')
  await page.getByRole('button', { name: 'Disable account' }).last().click()
  await expect(page.getByText('Disabled', { exact: true }).first()).toBeVisible()
  expect(statusPayload).toEqual({ enabled: false, confirmation: 'Grid Operations' })
  expect(issues).toEqual([])
})
