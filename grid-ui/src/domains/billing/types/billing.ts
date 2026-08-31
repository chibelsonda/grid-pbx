import type {
  BillingProjection,
  InvoiceDocumentSummary,
  PaymentConfirmation,
  ReceiptDocumentSummary,
  ServiceOverview,
} from '@/domains/services/types/service'

export type { BillingInvoiceDetail, BillingReceiptDetail } from '@/domains/services/types/service'

export type BillingWorkspace = Pick<
  ServiceOverview,
  | 'id'
  | 'standing'
  | 'reseller'
  | 'billing_cycle'
  | 'billing_impact'
  | 'billing'
  | 'reconciliation'
  | 'documents'
  | 'last_synced_at'
  | 'sync_status'
>

export type BillingRecord =
  | {
      kind: 'invoice'
      source: string
      authoritative: boolean
      item: InvoiceDocumentSummary
    }
  | {
      kind: 'receipt'
      source: string
      authoritative: boolean
      item: ReceiptDocumentSummary
    }
  | {
      kind: 'payment_confirmation'
      source: string
      authoritative: false
      item: PaymentConfirmation
    }
  | {
      kind: 'switch_transaction'
      source: 'switch_projection'
      authoritative: false
      item: NonNullable<BillingProjection>['transactions'][number]
    }
