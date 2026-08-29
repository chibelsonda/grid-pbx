export type SyncState = {
  status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_successful_at: string | null
  error_message: string | null
}

export type AssignedCallflow = {
  id: string
  name: string | null
  numbers: string[]
}

export type PhoneNumber = {
  id: string
  number: string
  state: string | null
  used_by: string | null
  carrier_name: string | null
  features: string[]
  cnam: {
    display_name: string | null
    inbound_lookup: boolean
  }
  e911: {
    status: string | null
    caller_name: string | null
    street_address: string | null
    extended_address: string | null
    locality: string | null
    region: string | null
    postal_code: string | null
    notification_contact_emails: string[]
  }
  porting: {
    active: boolean
    requested_port_date: string | null
    service_provider: string | null
  }
  capabilities: {
    available_features: string[]
    cnam: PhoneNumberOperationCapability
    e911: PhoneNumberOperationCapability
    porting: PhoneNumberOperationCapability
    purchasing: PhoneNumberOperationCapability
    release: PhoneNumberOperationCapability
  }
  assigned_callflow: AssignedCallflow | null
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}

export type PhoneNumberOperationCapability = {
  available: boolean
  writable: boolean
  reason: string
}

export type PhoneNumberFilters = {
  search: string
  state: string
  assignment: '' | 'assigned' | 'unassigned'
  feature: string
}

export type SyncRun = {
  id: string
  resource_type: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
