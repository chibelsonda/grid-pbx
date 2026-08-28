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
  e911_status: string | null
  assigned_callflow: AssignedCallflow | null
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
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
