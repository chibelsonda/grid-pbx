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

const phoneNumber = {
  id: '2baf74c0-70dc-486f-a345-e910034e032c',
  number: '+15551234567',
  state: 'port_in',
  used_by: 'callflow',
  carrier_name: 'Test Carrier',
  features: ['inbound_cnam', 'e911'],
  cnam: { display_name: 'GridPBX', inbound_lookup: true },
  e911: {
    status: 'PROVISIONED',
    caller_name: 'GridPBX Reception',
    street_address: '100 Main Street',
    extended_address: 'Suite 200',
    locality: 'San Francisco',
    region: 'CA',
    postal_code: '94105',
    notification_contact_emails: ['ops@example.test'],
  },
  porting: {
    active: true,
    requested_port_date: '2026-09-15',
    service_provider: 'Example Carrier',
  },
  capabilities: {
    available_features: ['cnam', 'e911', 'port'],
    cnam: {
      available: true,
      writable: false,
      reason:
        'Switch reports CNAM as selectable, but the installed notifier workflow does not confirm carrier completion. Mutation remains disabled pending approved quote, charge-confirmation, audit, and reconciliation policy.',
    },
    e911: {
      available: true,
      writable: false,
      reason:
        'Switch reports E911 as selectable, but GridPBX has not confirmed provider readiness or emergency-caller-ID safeguards. Mutation remains disabled pending approved emergency-service, billing, confirmation, audit, and reconciliation policy.',
    },
    porting: { available: true, writable: false, reason: 'Porting policy required.' },
    purchasing: { available: false, writable: false, reason: 'Carrier required.' },
    release: { available: false, writable: false, reason: 'Carrier required.' },
  },
  assigned_callflow: null,
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T09:00:00+08:00',
}

test('shows allowlisted Phone Number feature details and explicit operation gates', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  await page.route('**/api/v1/accounts/*/phone-numbers?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [phoneNumber],
        links: { first: null, last: null, prev: null, next: null },
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          sync: { status: 'healthy', last_successful_at: null, error_message: null },
        },
      }),
    }),
  )
  await page.route(`**/api/v1/accounts/*/phone-numbers/${phoneNumber.id}`, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: phoneNumber }),
    }),
  )

  await page.goto('/phone-numbers')
  await expect(page.getByRole('heading', { name: 'Phone Numbers', exact: true })).toBeVisible()
  await page.getByRole('button', { name: `View ${phoneNumber.number}` }).click()

  const dialog = page.getByRole('dialog', { name: phoneNumber.number })
  await expect(dialog.getByText('100 Main Street, Suite 200')).toBeVisible()
  await expect(dialog.getByText('ops@example.test', { exact: false })).toBeVisible()
  await expect(dialog.getByText('Example Carrier')).toBeVisible()
  await expect(dialog.getByText('Caller name (CNAM)')).toBeVisible()
  await expect(dialog.getByText(/does not confirm carrier completion/)).toBeVisible()
  await expect(dialog.getByText(/has not confirmed provider readiness/)).toBeVisible()
  await expect(dialog.getByText('Policy gated', { exact: true })).toHaveCount(3)
  await expect(dialog.getByRole('button', { name: /purchase|release|port/i })).toHaveCount(0)
  expect(issues).toEqual([])
})
