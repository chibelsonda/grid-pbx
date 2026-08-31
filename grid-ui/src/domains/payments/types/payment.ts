export type PaymentCapability = {
  enabled: boolean
  provider: string
  environment: 'sandbox' | 'unsupported'
  configured: boolean
  capture_strategy: 'hosted_or_tokenized'
  server_accepts_card_data: false
  client: {
    available: boolean
    accept_ui_url: string | null
    api_login_id: string | null
    public_client_key: string | null
    sandbox_max_charge_minor: number | null
    sandbox_max_refund_minor: number | null
  }
  mutations: {
    attach_payment_method: boolean
    charge: boolean
    void: boolean
    refund: boolean
  }
}

export type PaymentOpaqueData = {
  dataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT'
  dataValue: string
}

export type PaymentAttempt = {
  id: string
  source_attempt_id: string | null
  provider: string
  operation: 'charge' | 'refund' | 'void' | 'attach_payment_method'
  amount: string | null
  currency: 'USD' | null
  status: 'pending' | 'succeeded' | 'failed' | 'indeterminate' | 'cancelled'
  safe_error_code: string | null
  completed_at: string | null
  created_at: string | null
}

export type PaymentCustomerProfile = {
  id: string
  provider: string
  status: string
  masked_account: string | null
  account_type: string | null
  created_at: string | null
}

export type PaymentProfileOutcome = {
  attempt: PaymentAttempt
  profile: PaymentCustomerProfile | null
}
