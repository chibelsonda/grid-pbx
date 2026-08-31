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

async function routeBillingWorkspace(
  page: Page,
  invoices: Array<Record<string, unknown>>,
  receipts: Array<Record<string, unknown>>,
): Promise<void> {
  await page.route('**/api/v1/accounts/*/services', (route) =>
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 'billing-workspace-id',
          standing: { acceptable: true, reason: null },
          reseller: {
            is_reseller: false,
            billing_account: null,
            billing_account_projected: true,
          },
          billing_cycle: { next_at: null, period: 1, unit: 'month' },
          billing_impact: {
            invoice_count: invoices.length,
            due_today: 100.25,
            recurring_amount: 150.5,
          },
          billing: null,
          reconciliation: { status: 'healthy', checks: [], sync_history: [] },
          documents: {
            invoices: {
              available: invoices.length > 0,
              authoritative: invoices.length > 0,
              source: invoices.length > 0 ? 'test_authority' : 'unconfigured',
              reported_count: invoices.length,
              items: invoices,
              guidance: 'Invoice source status.',
            },
            receipts: {
              available: receipts.length > 0,
              authoritative: receipts.length > 0,
              source: receipts.length > 0 ? 'test_authority' : 'unconfigured',
              items: receipts,
              guidance: 'Receipt source status.',
            },
            payment_confirmations: {
              available: true,
              authoritative: false,
              source: 'gridpbx_payment_attempts',
              items: [],
              guidance: 'Payment confirmations are not receipts.',
            },
          },
          plans: [],
          quantities: [],
          limits: null,
          last_synced_at: null,
          sync_status: 'healthy',
        },
      }),
    }),
  )
}

test('shows the account-scoped read-only billing workspace', async ({ page }) => {
  const issues = collectPageIssues(page)

  await page.goto('/billing')

  await expect(page.getByRole('heading', { level: 1, name: 'Billing' })).toBeVisible()
  await expect(page.getByText('Invoice source', { exact: true })).toBeVisible()
  await expect(page.getByText('Receipt source', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Invoices' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Payment confirmations' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Reconciliation health' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Recent Switch billing activity' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Sandbox payment verification' })).toBeVisible()
  await expect(page.getByRole('button', { name: /production/i })).toHaveCount(0)
  await expect(page.getByText(/SQLSTATE|password|provider_reference/i)).toHaveCount(0)

  expect(issues).toEqual([])
})

test('keeps Services compact and links to the dedicated billing workspace', async ({ page }) => {
  const issues = collectPageIssues(page)

  await page.goto('/services')
  await expect(page.getByRole('heading', { level: 1, name: 'Services & limits' })).toBeVisible()
  await page.getByRole('button', { name: 'View details' }).click()

  const panel = page.getByRole('dialog', { name: 'Service details' })
  await expect(panel.getByRole('heading', { name: 'Billing workspace' })).toBeVisible()
  await expect(panel.getByRole('tab', { name: /basic|advanced/i })).toHaveCount(0)
  await expect(
    panel.getByRole('button', { name: /charge|refund|credit|debit|top.?up/i }),
  ).toHaveCount(0)
  await expect(panel.getByText('Billing documents', { exact: true })).toHaveCount(0)
  await expect(panel.getByText('Switch billing activity', { exact: true })).toHaveCount(0)
  await panel.getByRole('link', { name: 'Open billing workspace' }).click()

  await expect(page).toHaveURL(/\/billing$/)
  await expect(page.getByRole('heading', { level: 1, name: 'Billing' })).toBeVisible()
  expect(issues).toEqual([])
})

test('loads provider-neutral invoice detail before offering its safe PDF download', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const invoiceId = '96d7161d-438d-48fc-a69f-03d68f6f4f51'
  const invoice = {
    id: invoiceId,
    number: 'INV-E2E-100',
    status: 'open',
    currency: 'USD',
    total: '150.50',
    amount_paid: '50.25',
    amount_due: '100.25',
    issued_at: '2026-08-01',
    due_at: '2026-08-31',
    document_available: true,
  }

  await routeBillingWorkspace(page, [invoice], [])
  await page.route(`**/billing/invoices/${invoiceId}`, (route) =>
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          ...invoice,
          authoritative: true,
          source: 'test_authority',
          line_items: { available: false, items: [] },
          document: { available: true, content_type: 'application/pdf' },
        },
      }),
    }),
  )
  await page.route(`**/billing/invoices/${invoiceId}/document`, (route) =>
    route.fulfill({
      contentType: 'application/pdf',
      headers: {
        'Content-Disposition': `attachment; filename="invoice-${invoiceId}.pdf"`,
      },
      body: Buffer.from('%PDF-1.7'),
    }),
  )

  await page.goto('/billing')
  await page.getByText('INV-E2E-100', { exact: true }).click()

  const panel = page.getByRole('dialog', { name: 'INV-E2E-100' })
  await expect(panel.getByRole('tab', { name: /basic|advanced/i })).toHaveCount(0)
  await expect(panel.getByRole('button', { name: /edit|delete|charge|refund/i })).toHaveCount(0)
  await expect(panel.getByRole('button', { name: 'Download invoice PDF' })).toBeVisible()
  const downloadPromise = page.waitForEvent('download')
  await panel.getByRole('button', { name: 'Download invoice PDF' }).click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toBe(`invoice-${invoiceId}.pdf`)
  expect(issues).toEqual([])
})

test('keeps provider receipts separate and safely downloads their confirmed PDF', async ({
  page,
}) => {
  const issues = collectPageIssues(page)
  const receiptId = '6eb271ad-d3a0-474a-abce-7af6e703de31'
  const receipt = {
    id: receiptId,
    number: 'RCT-E2E-100',
    status: 'settled',
    currency: 'USD',
    amount: '50.25',
    paid_at: '2026-08-15T12:00:00Z',
    document_available: true,
  }

  await routeBillingWorkspace(page, [], [receipt])
  await page.route(`**/billing/receipts/${receiptId}`, (route) =>
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          ...receipt,
          authoritative: true,
          source: 'test_authority',
          document: { available: true, content_type: 'application/pdf' },
        },
      }),
    }),
  )
  await page.route(`**/billing/receipts/${receiptId}/document`, (route) =>
    route.fulfill({
      contentType: 'application/pdf',
      headers: {
        'Content-Disposition': `attachment; filename="receipt-${receiptId}.pdf"`,
      },
      body: Buffer.from('%PDF-1.7'),
    }),
  )

  await page.goto('/billing')
  await page.getByText('RCT-E2E-100', { exact: true }).click()

  await expect(page.getByRole('button', { name: 'Download receipt PDF' })).toBeVisible()
  await expect(page.getByText('Provider-issued receipts only.', { exact: false })).toBeVisible()
  const downloadPromise = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Download receipt PDF' }).click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toBe(`receipt-${receiptId}.pdf`)
  expect(issues).toEqual([])
})
