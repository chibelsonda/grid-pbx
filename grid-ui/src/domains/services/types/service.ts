export type ServicePlan = {
  id: string
  name: string | null
  description: string | null
  category: string | null
}
export type ServiceQuantity = {
  id: string
  scope: 'account' | 'cascade' | 'manual'
  category: string
  item: string
  quantity: number
}
export type ServiceLimits = {
  id: string
  enabled: boolean
  allow_prepay: boolean
  allow_postpay: boolean
  inbound_trunks: number
  outbound_trunks: number
  twoway_trunks: number
  burst_trunks: number
  calls: number | null
  resource_consuming_calls: number | null
  soft_limit_inbound: boolean
  soft_limit_outbound: boolean
}
export type BillingProjection = {
  id: string
  ledger_total: string | null
  ledger_source_count: number
  transaction_count: number
  availability: {
    ledgers: boolean
    ledger_total: boolean
    transactions: boolean
  }
  ledger_summaries: Array<{
    id: string
    source_service: string
    amount: string
    usage_quantity: string | null
    usage_type: string | null
    usage_unit: string | null
  }>
  transactions: Array<{
    id: string
    amount: string
    type: string | null
    reason: string | null
    description: string | null
    code: number | null
    created_at: string | null
  }>
  last_synced_at: string | null
}
export type ReconciliationCheckStatus = 'passed' | 'warning' | 'failed'
export type ServiceReconciliation = {
  status: 'healthy' | 'attention' | 'error'
  checks: Array<{
    code: string
    label: string
    status: ReconciliationCheckStatus
    message: string
    guidance: string
    expected_count: number | null
    actual_count: number | null
  }>
  sync_history: Array<{
    id: string
    status: 'queued' | 'running' | 'succeeded' | 'failed'
    processed_count: number
    failure_category:
      'authentication' | 'switch_request' | 'response_validation' | 'synchronization' | null
    message: string | null
    guidance: string | null
    started_at: string | null
    finished_at: string | null
    created_at: string | null
  }>
}
export type PaymentConfirmation = {
  id: string
  source_attempt_id: string | null
  provider: string
  operation: 'charge' | 'refund'
  amount: string | null
  currency: string | null
  status: 'succeeded'
  completed_at: string | null
}
export type InvoiceDocumentSummary = {
  id: string
  number: string | null
  status: string
  currency: string | null
  total: string
  amount_paid: string
  amount_due: string
  issued_at: string | null
  due_at: string | null
  document_available: boolean
}
export type BillingInvoiceDetail = InvoiceDocumentSummary & {
  authoritative: boolean
  source: string
  line_items: {
    available: boolean
    items: Array<Record<string, unknown>>
  }
  document: {
    available: boolean
    content_type: 'application/pdf' | null
  }
}
export type ReceiptDocumentSummary = {
  id: string
  number: string | null
  status: string
  currency: string | null
  amount: string
  paid_at: string | null
  document_available: boolean
}
export type BillingReceiptDetail = ReceiptDocumentSummary & {
  authoritative: boolean
  source: string
  document: {
    available: boolean
    content_type: 'application/pdf' | null
  }
}
export type BillingDocuments = {
  invoices: {
    available: boolean
    authoritative: boolean
    source: string
    reported_count: number
    items: InvoiceDocumentSummary[]
    guidance: string
  }
  receipts: {
    available: boolean
    authoritative: boolean
    source: string
    items: ReceiptDocumentSummary[]
    guidance: string
  }
  payment_confirmations: {
    available: true
    authoritative: false
    source: 'gridpbx_payment_attempts'
    items: PaymentConfirmation[]
    guidance: string
  }
}
export type ServiceOverview = {
  id: string
  standing: { acceptable: boolean; reason: string | null }
  reseller: {
    is_reseller: boolean
    billing_account: { id: string; name: string; realm: string | null } | null
    billing_account_projected: boolean
  }
  billing_cycle: { next_at: string | null; period: number; unit: string | null }
  billing_impact: { invoice_count: number; due_today: number; recurring_amount: number }
  billing: BillingProjection | null
  reconciliation: ServiceReconciliation
  documents: BillingDocuments
  plans: ServicePlan[]
  quantities: ServiceQuantity[]
  limits: ServiceLimits | null
  last_synced_at: string | null
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
}
export type ServiceSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
