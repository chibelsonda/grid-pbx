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

test('shows polished safe read-only operational capability cards', async ({ page }, testInfo) => {
  const issues = collectPageIssues(page)
  const mutations: string[] = []
  page.on('request', (request) => {
    if (
      ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) &&
      /\/api\/v1\/accounts\/[^/]+\//.test(new URL(request.url()).pathname)
    ) {
      mutations.push(`${request.method()} ${request.url()}`)
    }
  })
  const responsePromise = page.waitForResponse((response) =>
    /\/api\/v1\/accounts\/[^/]+\/operational-status$/.test(new URL(response.url()).pathname),
  )

  await page.goto('/system-status')
  await expect(page.getByRole('heading', { name: 'System status' })).toBeVisible()
  const response = await responsePromise
  const payload = (await response.json()) as {
    data: {
      observed_at: string
      presence: {
        subscription_diagnostics_available: boolean
        live_status_available: false
        commands_available: false
      }
      parking: {
        summary_available: boolean
        active_call_count: number | null
        actions_available: false
      }
      webhooks: {
        event_catalog_available: boolean
        available_event_count: number | null
        configuration_summary_available: boolean
        configured_count: number | null
        enabled_count: number | null
        configuration_mutations_available: false
        delivery_history_available: false
      }
      messaging: {
        sms_inventory_available: boolean
        mms_inventory_available: boolean
        message_content_available: false
        sending_available: false
      }
      number_porting: {
        inventory_available: boolean
        request_details_available: false
        documents_available: false
        workflow_mutations_available: false
      }
      number_management: {
        carrier_configuration_available: boolean
        search_available: false
        purchase_available: false
        reservation_available: false
        release_available: false
      }
    }
  }

  expect(response.status()).toBe(200)
  expect(Object.keys(payload.data).sort()).toEqual([
    'messaging',
    'number_management',
    'number_porting',
    'observed_at',
    'parking',
    'presence',
    'webhooks',
  ])
  expect(Object.keys(payload.data.presence).sort()).toEqual([
    'commands_available',
    'live_status_available',
    'subscription_diagnostics_available',
  ])
  expect(Object.keys(payload.data.parking).sort()).toEqual([
    'actions_available',
    'active_call_count',
    'summary_available',
  ])
  expect(payload.data.presence.live_status_available).toBe(false)
  expect(payload.data.presence.commands_available).toBe(false)
  expect(payload.data.parking.actions_available).toBe(false)
  expect(Object.keys(payload.data.webhooks).sort()).toEqual([
    'available_event_count',
    'configuration_mutations_available',
    'configuration_summary_available',
    'configured_count',
    'delivery_history_available',
    'enabled_count',
    'event_catalog_available',
  ])
  expect(payload.data.webhooks.configuration_mutations_available).toBe(false)
  expect(payload.data.webhooks.delivery_history_available).toBe(false)
  expect(Object.keys(payload.data.messaging).sort()).toEqual([
    'message_content_available',
    'mms_inventory_available',
    'sending_available',
    'sms_inventory_available',
  ])
  expect(payload.data.messaging.sms_inventory_available).toBe(false)
  expect(payload.data.messaging.mms_inventory_available).toBe(false)
  expect(payload.data.messaging.message_content_available).toBe(false)
  expect(payload.data.messaging.sending_available).toBe(false)
  expect(Object.keys(payload.data.number_porting).sort()).toEqual([
    'documents_available',
    'inventory_available',
    'request_details_available',
    'workflow_mutations_available',
  ])
  expect(payload.data.number_porting.inventory_available).toBe(true)
  expect(payload.data.number_porting.request_details_available).toBe(false)
  expect(payload.data.number_porting.documents_available).toBe(false)
  expect(payload.data.number_porting.workflow_mutations_available).toBe(false)
  expect(Object.keys(payload.data.number_management).sort()).toEqual([
    'carrier_configuration_available',
    'purchase_available',
    'release_available',
    'reservation_available',
    'search_available',
  ])
  expect(payload.data.number_management.carrier_configuration_available).toBe(true)
  expect(payload.data.number_management.search_available).toBe(false)
  expect(payload.data.number_management.purchase_available).toBe(false)
  expect(payload.data.number_management.reservation_available).toBe(false)
  expect(payload.data.number_management.release_available).toBe(false)
  expect(JSON.stringify(payload)).not.toMatch(
    /Call-ID|Presence-ID|Switch-URI|contact|subscription_id|switch_account_id|hook_id|req_body|resp_body|uri|message_id|"body"|"from"|"to"|attachment|port_request_id|billing_account|signee|signing_date|transfer_date|port_authority|comments|uploads|"pin"|usable_carriers|usable_creation_states|carrier_modules|available_numbers|accept_charges|quotes/i,
  )

  await expect(page.getByRole('heading', { name: 'Presence' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Parked calls' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Webhooks' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'SMS / MMS' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Number porting' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Number acquisition' })).toBeVisible()
  await expect(page.getByRole('tab', { name: /basic|advanced/i })).toHaveCount(0)
  await expect(
    page.getByRole('button', {
      name: /restart|enable|disable|create|edit|delete|send|purchase|release|reserve|submit|cancel|complete/i,
    }),
  ).toHaveCount(0)
  await expect(
    page.getByText('Live presence status and set/reset commands remain capability-gated.'),
  ).toBeVisible()
  await expect(page.getByText(/Only the aggregate count is exposed/)).toBeVisible()
  await expect(
    page.getByText(/Park and retrieve actions require an active phone call/),
  ).toBeVisible()
  await expect(
    page.getByText(/URLs, custom data, raw IDs, and delivery payloads remain private/),
  ).toBeVisible()
  await expect(
    page.getByText(/Configuration changes and delivery history remain capability-gated/),
  ).toBeVisible()
  await expect(page.getByText(/Only endpoint availability is reported/)).toBeVisible()
  await expect(page.getByText(/Sending and message content remain capability-gated/)).toBeVisible()
  await expect(page.getByText(/Only collection availability is reported/)).toBeVisible()
  await expect(page.getByText(/Create, submit, schedule, complete, cancel/)).toBeVisible()
  await expect(
    page.getByText(/Only the account-scoped carrier configuration endpoint shape/),
  ).toBeVisible()
  await expect(page.getByText(/Search, purchase, reservation, and release remain/)).toBeVisible()
  await expect(page.getByText(/Observed/)).toBeVisible()

  const cards = page.locator('[data-operational-status-card]')
  await expect(cards).toHaveCount(6)
  for (let index = 0; index < (await cards.count()); index += 1) {
    const card = cards.nth(index)
    const layout = await card.evaluate((element) => {
      const header = element.firstElementChild
      const icon = header?.firstElementChild
      const body = header?.nextElementSibling
      const iconStyle = icon ? window.getComputedStyle(icon) : null

      return {
        icon: icon?.getBoundingClientRect().left ?? -1,
        body: body?.getBoundingClientRect().left ?? -2,
        iconBackground: iconStyle?.backgroundColor,
        iconBorderRadius: iconStyle?.borderRadius,
      }
    })
    expect(
      Math.abs(layout.icon - layout.body),
      `card ${index}: icon=${layout.icon}, body=${layout.body}`,
    ).toBeLessThan(2)
    expect(layout.iconBackground, `card ${index} icon background`).toBe('rgba(0, 0, 0, 0)')
    expect(layout.iconBorderRadius, `card ${index} icon border radius`).toBe('0px')
  }
  await page.screenshot({ path: testInfo.outputPath('system-status-desktop.png'), fullPage: true })

  await page.setViewportSize({ width: 390, height: 844 })
  await page.reload()
  await expect(page.getByRole('heading', { name: 'Presence' })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.screenshot({ path: testInfo.outputPath('system-status-mobile.png'), fullPage: true })

  expect(mutations).toEqual([])
  expect(issues).toEqual([])
})
