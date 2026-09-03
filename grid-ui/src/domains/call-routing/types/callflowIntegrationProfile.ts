type CallflowIntegrationProfileBase = {
  id: string
  name: string
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export type PivotIntegrationProfile = CallflowIntegrationProfileBase & {
  integration_type: 'pivot'
  configuration: {
    methods: Array<'get' | 'post'>
    formats: Array<'switch' | 'twiml'>
    has_cdr_callback: boolean
    has_custom_headers: boolean
  }
}

export type WebhookIntegrationProfile = CallflowIntegrationProfileBase & {
  integration_type: 'webhook'
  configuration: {
    methods: Array<'get' | 'post'>
    max_retries: number
  }
}

export type DisaIntegrationProfile = CallflowIntegrationProfileBase & {
  integration_type: 'disa'
  configuration: {
    pin_configured: boolean
    retries: number
    interdigit_ms: number
    max_digits: number
    preconnect_audio: 'dialtone' | 'ringing'
    enforce_call_restriction: true
    use_account_caller_id: false
  }
}

export type CarrierIntegrationProfile = CallflowIntegrationProfileBase & {
  integration_type: 'global_carrier' | 'account_carrier'
  configuration: {
    route_scope: 'global' | 'account' | 'reseller'
  }
}

export type CallflowIntegrationProfile =
  | PivotIntegrationProfile
  | WebhookIntegrationProfile
  | DisaIntegrationProfile
  | CarrierIntegrationProfile

export type PivotIntegrationProfileInput = {
  integration_type: 'pivot'
  name: string
  is_active: boolean
  settings: {
    voice_url: string
    cdr_url: string | null
    methods: Array<'get' | 'post'>
    formats: Array<'switch' | 'twiml'>
    req_body_format: 'form' | 'json'
    req_timeout_ms: number
    custom_request_headers: Record<string, string>
  }
}

export type WebhookIntegrationProfileInput = {
  integration_type: 'webhook'
  name: string
  is_active: boolean
  settings: {
    uri: string
    methods: Array<'get' | 'post'>
    max_retries: number
  }
}

export type DisaIntegrationProfileInput = {
  integration_type: 'disa'
  name: string
  is_active: boolean
  settings: {
    pin: string
    retries: number
    interdigit_ms: number
    max_digits: number
    preconnect_audio: 'dialtone' | 'ringing'
  }
}

export type CarrierIntegrationProfileInput = {
  integration_type: 'global_carrier' | 'account_carrier'
  name: string
  is_active: boolean
  settings: { scope: 'account' | 'reseller' } | Record<string, never>
}

export type CallflowIntegrationProfileInput =
  | PivotIntegrationProfileInput
  | WebhookIntegrationProfileInput
  | DisaIntegrationProfileInput
  | CarrierIntegrationProfileInput

export type CallflowIntegrationProfileMetadataInput = {
  name?: string
  is_active?: boolean
}
