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
export type ServiceOverview = {
  id: string
  standing: { acceptable: boolean; reason: string | null }
  reseller: { is_reseller: boolean }
  billing_cycle: { next_at: string | null; period: number; unit: string | null }
  billing_impact: { invoice_count: number; due_today: number; recurring_amount: number }
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
