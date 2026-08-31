import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/shared/api/http'
import { billingApi } from './billingApi'
import type { BillingInvoiceDetail, BillingReceiptDetail } from '../types/billing'

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

describe('billing API', () => {
  afterEach(() => vi.restoreAllMocks())

  it('loads invoice detail and binary content through public account-scoped ids', async () => {
    const pdf = new Blob(['%PDF'], { type: 'application/pdf' })
    const get = vi
      .spyOn(http, 'get')
      .mockResolvedValueOnce({ data: { data: invoice } })
      .mockResolvedValueOnce({ data: pdf })

    const detail = await billingApi.invoice('account-public-id', invoice.id)
    const document = await billingApi.invoiceDocument('account-public-id', invoice.id)

    expect(get).toHaveBeenNthCalledWith(
      1,
      `/api/v1/accounts/account-public-id/billing/invoices/${invoice.id}`,
    )
    expect(get).toHaveBeenNthCalledWith(
      2,
      `/api/v1/accounts/account-public-id/billing/invoices/${invoice.id}/document`,
      { responseType: 'blob' },
    )
    expect(detail).toEqual(invoice)
    expect(document).toBe(pdf)
    expect(JSON.stringify(get.mock.calls)).not.toMatch(/provider_reference|legacy_invoice_id/i)
  })

  it('loads receipt detail and binary content through public account-scoped ids', async () => {
    const pdf = new Blob(['%PDF'], { type: 'application/pdf' })
    const get = vi
      .spyOn(http, 'get')
      .mockResolvedValueOnce({ data: { data: receipt } })
      .mockResolvedValueOnce({ data: pdf })

    const detail = await billingApi.receipt('account-public-id', receipt.id)
    const document = await billingApi.receiptDocument('account-public-id', receipt.id)

    expect(get).toHaveBeenNthCalledWith(
      1,
      `/api/v1/accounts/account-public-id/billing/receipts/${receipt.id}`,
    )
    expect(get).toHaveBeenNthCalledWith(
      2,
      `/api/v1/accounts/account-public-id/billing/receipts/${receipt.id}/document`,
      { responseType: 'blob' },
    )
    expect(detail).toEqual(receipt)
    expect(document).toBe(pdf)
    expect(JSON.stringify(get.mock.calls)).not.toMatch(/provider_reference|payment_profile_id/i)
  })
})
