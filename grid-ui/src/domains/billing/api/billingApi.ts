import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { BillingInvoiceDetail, BillingReceiptDetail, BillingWorkspace } from '../types/billing'

export const billingApi = {
  async overview(accountId: string): Promise<BillingWorkspace | null> {
    return unwrapApiData(
      await http.get<ApiResponse<BillingWorkspace | null>>(
        `/api/v1/accounts/${accountId}/services`,
      ),
    )
  },
  async invoice(accountId: string, invoiceId: string): Promise<BillingInvoiceDetail> {
    return unwrapApiData(
      await http.get<ApiResponse<BillingInvoiceDetail>>(
        `/api/v1/accounts/${accountId}/billing/invoices/${invoiceId}`,
      ),
    )
  },
  async invoiceDocument(accountId: string, invoiceId: string): Promise<Blob> {
    return (
      await http.get(`/api/v1/accounts/${accountId}/billing/invoices/${invoiceId}/document`, {
        responseType: 'blob',
      })
    ).data
  },
  async receipt(accountId: string, receiptId: string): Promise<BillingReceiptDetail> {
    return unwrapApiData(
      await http.get<ApiResponse<BillingReceiptDetail>>(
        `/api/v1/accounts/${accountId}/billing/receipts/${receiptId}`,
      ),
    )
  },
  async receiptDocument(accountId: string, receiptId: string): Promise<Blob> {
    return (
      await http.get(`/api/v1/accounts/${accountId}/billing/receipts/${receiptId}/document`, {
        responseType: 'blob',
      })
    ).data
  },
}
