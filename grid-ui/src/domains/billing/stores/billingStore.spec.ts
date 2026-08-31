import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { billingApi } from '../api/billingApi'
import { useBillingStore } from './billingStore'
import type { BillingInvoiceDetail, BillingReceiptDetail, BillingWorkspace } from '../types/billing'

vi.mock('../api/billingApi', () => ({
  billingApi: {
    overview: vi.fn(),
    invoice: vi.fn(),
    invoiceDocument: vi.fn(),
    receipt: vi.fn(),
    receiptDocument: vi.fn(),
  },
}))

const invoice: BillingInvoiceDetail = {
  id: '96d7161d-438d-48fc-a69f-03d68f6f4f51',
  number: 'INV-2026-100',
  status: 'open',
  currency: 'USD',
  total: '150.50',
  amount_paid: '50.25',
  amount_due: '100.25',
  issued_at: '2026-08-01',
  due_at: '2026-08-31',
  document_available: true,
  authoritative: true,
  source: 'test_authority',
  line_items: { available: false, items: [] },
  document: { available: true, content_type: 'application/pdf' },
}

const receipt: BillingReceiptDetail = {
  id: '6eb271ad-d3a0-474a-abce-7af6e703de31',
  number: 'RCT-2026-100',
  status: 'settled',
  currency: 'USD',
  amount: '50.25',
  paid_at: '2026-08-15T12:00:00Z',
  document_available: true,
  authoritative: true,
  source: 'test_authority',
  document: { available: true, content_type: 'application/pdf' },
}

const overview: BillingWorkspace = {
  id: 'billing-workspace-public-id',
  standing: { acceptable: true, reason: null },
  reseller: { is_reseller: false, billing_account: null, billing_account_projected: true },
  billing_cycle: { next_at: null, period: 1, unit: 'month' },
  billing_impact: { invoice_count: 1, due_today: 100.25, recurring_amount: 150.5 },
  billing: null,
  reconciliation: { status: 'healthy', checks: [], sync_history: [] },
  documents: {
    invoices: {
      available: false,
      authoritative: false,
      source: 'unconfigured',
      reported_count: 1,
      items: [],
      guidance: 'An authoritative source is not configured.',
    },
    receipts: {
      available: false,
      authoritative: false,
      source: 'unconfigured',
      items: [],
      guidance: 'A receipt source is not configured.',
    },
    payment_confirmations: {
      available: true,
      authoritative: false,
      source: 'gridpbx_payment_attempts',
      items: [],
      guidance: 'Confirmations are not receipts.',
    },
  },
  last_synced_at: null,
  sync_status: 'healthy',
}

describe('billingStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn(() => 'blob:invoice'),
      revokeObjectURL: vi.fn(),
    })
  })

  afterEach(() => vi.unstubAllGlobals())

  it('loads the account-scoped billing workspace without starting synchronization', async () => {
    vi.mocked(billingApi.overview).mockResolvedValue(overview)
    const store = useBillingStore()

    await store.load('account-public-id')

    expect(billingApi.overview).toHaveBeenCalledWith('account-public-id')
    expect(store.overview).toEqual(overview)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('clears account-specific state when the selected account changes', () => {
    const store = useBillingStore()
    store.overview = overview
    store.error = 'Previous account error'

    store.reset()

    expect(store.overview).toBeNull()
    expect(store.error).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('loads invoice detail and downloads only through its public id', async () => {
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
    vi.mocked(billingApi.invoice).mockResolvedValue(invoice)
    vi.mocked(billingApi.invoiceDocument).mockResolvedValue(
      new Blob(['%PDF'], { type: 'application/pdf' }),
    )
    const store = useBillingStore()

    await store.loadInvoice('account-public-id', invoice.id)
    await store.downloadInvoice('account-public-id', invoice.id)

    expect(billingApi.invoice).toHaveBeenCalledWith('account-public-id', invoice.id)
    expect(billingApi.invoiceDocument).toHaveBeenCalledWith('account-public-id', invoice.id)
    expect(store.invoiceDetail).toEqual(invoice)
    expect(store.invoiceError).toBeNull()
    expect(click).toHaveBeenCalledOnce()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:invoice')
  })

  it('loads receipt detail and downloads only through its public id', async () => {
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
    vi.mocked(billingApi.receipt).mockResolvedValue(receipt)
    vi.mocked(billingApi.receiptDocument).mockResolvedValue(
      new Blob(['%PDF'], { type: 'application/pdf' }),
    )
    const store = useBillingStore()

    await store.loadReceipt('account-public-id', receipt.id)
    await store.downloadReceipt('account-public-id', receipt.id)

    expect(billingApi.receipt).toHaveBeenCalledWith('account-public-id', receipt.id)
    expect(billingApi.receiptDocument).toHaveBeenCalledWith('account-public-id', receipt.id)
    expect(store.receiptDetail).toEqual(receipt)
    expect(store.receiptError).toBeNull()
    expect(click).toHaveBeenCalledOnce()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:invoice')
  })
})
