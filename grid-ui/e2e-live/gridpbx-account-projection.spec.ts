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
      call_restriction: { international: { action: 'deny' } },
      call_recording: {},
      dial_plan: { system: ['north_america'], rules: [] },
      formatters: [],
      preflow: { callflow_id: null, name: null, unresolved: false },
      metaflows: {
        binding_digit: '*',
        digit_timeout: 2000,
        listen_on: 'both',
        number_flow_count: 1,
        pattern_flow_count: 1,
        actions: [
          {
            trigger_type: 'number',
            trigger: '3',
            module: 'hangup',
            data: {},
            children: [],
          },
        ],
        locked_action_count: 1,
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
      advanced_routing: 'guided_rules_available',
      enable_disable: 'implemented_confirmed',
      billing_topup: 'provider_required',
    },
  })
  await page.route('**/api/v1/accounts/*/settings-options', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          restrictions: [
            { key: 'international', label: 'International', emergency: false },
            { key: 'emergency', label: 'Emergency', emergency: true },
          ],
          callflows: [
            {
              id: '20000000-0000-4000-8000-000000000001',
              name: 'Main inbound route',
              description: '2000',
            },
          ],
          metaflow_resources: {
            media: [],
            callflows: [
              {
                id: '20000000-0000-4000-8000-000000000001',
                name: 'Main inbound route',
                description: '2000',
              },
            ],
            devices: [],
            extensions: [],
          },
        },
      }),
    }),
  )
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
  await expect(page.getByText('Account call restrictions')).toBeVisible()
  await expect(page.getByText('Call-recording defaults')).toBeVisible()
  await expect(page.getByText('Dial plan and formatters')).toBeVisible()
  await expect(page.getByText('Preflow and in-call features')).toBeVisible()
  await expect(page.getByText('1 number trigger(s) and 1 pattern trigger(s)')).toBeVisible()
  await expect(page.getByText('Guided action trees')).toBeVisible()
  await expect(page.getByText('1 unsupported or unprojected action tree(s)')).toBeVisible()
  await page.getByRole('button', { name: 'Account preflow' }).click()
  await page.getByRole('option', { name: /Main inbound route/ }).click()
  await page.getByRole('button', { name: 'Outbound caller privacy' }).click()
  await page.getByRole('option', { name: 'Use Switch default' }).click()
  await page.getByRole('switch', { name: 'Off-net' }).first().click()
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
    outbound_privacy: null,
    caller_id: {
      external: {
        phone_number_id: '10000000-0000-4000-8000-000000000001',
      },
      emergency: {
        phone_number_id: '10000000-0000-4000-8000-000000000002',
      },
    },
    call_restriction: {
      international: { action: 'deny' },
      emergency: { action: 'inherit' },
    },
    call_recording: {
      account: { any: { offnet: { enabled: true, format: 'mp3' } } },
    },
    dial_plan: { system: ['north_america'], rules: [] },
    formatters: [],
    preflow: {
      callflow_id: '20000000-0000-4000-8000-000000000001',
      preserve_callflow: false,
    },
    metaflows: {
      binding_digit: '*',
      digit_timeout: 2000,
      listen_on: 'both',
      actions: [
        {
          trigger_type: 'number',
          trigger: '3',
          module: 'hangup',
          data: {},
          children: [],
        },
      ],
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
