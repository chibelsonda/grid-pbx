import axios from 'axios'
import { defineStore } from 'pinia'
import { billingApi } from '../api/billingApi'
import type { BillingInvoiceDetail, BillingReceiptDetail, BillingWorkspace } from '../types/billing'

const errorMessage = (error: unknown): string =>
  axios.isAxiosError(error)
    ? (error.response?.data?.message ?? 'Unable to load billing information.')
    : 'Unable to load billing information.'

const downloadPdf = (document: Blob, filename: string): void => {
  const url = URL.createObjectURL(document)

  try {
    const anchor = window.document.createElement('a')
    anchor.href = url
    anchor.download = filename
    anchor.click()
  } finally {
    URL.revokeObjectURL(url)
  }
}

export const useBillingStore = defineStore('billing', {
  state: () => ({
    overview: null as BillingWorkspace | null,
    invoiceDetail: null as BillingInvoiceDetail | null,
    invoiceRequestKey: null as string | null,
    invoiceLoading: false,
    invoiceDownloading: false,
    invoiceError: null as string | null,
    receiptDetail: null as BillingReceiptDetail | null,
    receiptRequestKey: null as string | null,
    receiptLoading: false,
    receiptDownloading: false,
    receiptError: null as string | null,
    loading: false,
    error: null as string | null,
  }),
  actions: {
    reset(): void {
      this.overview = null
      this.clearInvoice()
      this.clearReceipt()
      this.loading = false
      this.error = null
    },
    clearInvoice(): void {
      this.invoiceDetail = null
      this.invoiceRequestKey = null
      this.invoiceLoading = false
      this.invoiceDownloading = false
      this.invoiceError = null
    },
    clearReceipt(): void {
      this.receiptDetail = null
      this.receiptRequestKey = null
      this.receiptLoading = false
      this.receiptDownloading = false
      this.receiptError = null
    },
    async load(accountId: string): Promise<void> {
      this.loading = true
      this.error = null

      try {
        this.overview = await billingApi.overview(accountId)
      } catch (error) {
        this.error = errorMessage(error)
      } finally {
        this.loading = false
      }
    },
    async loadInvoice(accountId: string, invoiceId: string): Promise<void> {
      this.clearReceipt()
      const requestKey = `${accountId}:${invoiceId}`
      this.invoiceRequestKey = requestKey
      this.invoiceDetail = null
      this.invoiceLoading = true
      this.invoiceError = null

      try {
        const detail = await billingApi.invoice(accountId, invoiceId)
        if (this.invoiceRequestKey === requestKey) this.invoiceDetail = detail
      } catch (error) {
        if (this.invoiceRequestKey === requestKey) {
          this.invoiceError = errorMessage(error)
        }
      } finally {
        if (this.invoiceRequestKey === requestKey) this.invoiceLoading = false
      }
    },
    async downloadInvoice(accountId: string, invoiceId: string): Promise<void> {
      this.invoiceDownloading = true
      this.invoiceError = null

      try {
        downloadPdf(
          await billingApi.invoiceDocument(accountId, invoiceId),
          `invoice-${invoiceId}.pdf`,
        )
      } catch (error) {
        this.invoiceError = errorMessage(error)
      } finally {
        this.invoiceDownloading = false
      }
    },
    async loadReceipt(accountId: string, receiptId: string): Promise<void> {
      this.clearInvoice()
      const requestKey = `${accountId}:${receiptId}`
      this.receiptRequestKey = requestKey
      this.receiptDetail = null
      this.receiptLoading = true
      this.receiptError = null

      try {
        const detail = await billingApi.receipt(accountId, receiptId)
        if (this.receiptRequestKey === requestKey) this.receiptDetail = detail
      } catch (error) {
        if (this.receiptRequestKey === requestKey) {
          this.receiptError = errorMessage(error)
        }
      } finally {
        if (this.receiptRequestKey === requestKey) this.receiptLoading = false
      }
    },
    async downloadReceipt(accountId: string, receiptId: string): Promise<void> {
      this.receiptDownloading = true
      this.receiptError = null

      try {
        downloadPdf(
          await billingApi.receiptDocument(accountId, receiptId),
          `receipt-${receiptId}.pdf`,
        )
      } catch (error) {
        this.receiptError = errorMessage(error)
      } finally {
        this.receiptDownloading = false
      }
    },
  },
})
