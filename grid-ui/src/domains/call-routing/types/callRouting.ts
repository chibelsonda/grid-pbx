export type SyncState = {
  status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_successful_at: string | null
  error_message: string | null
  scope?: string
}

export type CallflowNode = {
  module: string
  target: {
    type: CallflowDestinationType
    id: string
    label: string
  } | null
  reference_status: 'resolved' | 'unresolved' | 'not_applicable'
  children: Record<string, CallflowNode>
}

export type CallflowDestinationType = 'extension' | 'device' | 'voicemail' | 'callflow' | 'media'

export type CallflowDestination = {
  id: string
  label: string
  detail: string | null
}

export type CallflowEditor = {
  mode: 'create' | 'update'
  editable: boolean
  blocked_reason: string | null
  destination_types: Array<{ value: CallflowDestinationType; label: string }>
  destinations: Record<CallflowDestinationType, CallflowDestination[]>
  phone_numbers: Array<{
    id: string
    number: string
    state: string | null
    selected: boolean
    available: boolean
    assigned_callflow: { id: string; name: string | null } | null
  }>
}

export type CallflowUpdate = {
  name: string
  destination_type: CallflowDestinationType
  destination_id: string
  phone_number_ids: string[]
}

export type Callflow = {
  id: string
  name: string | null
  route_type: 'extension' | 'phone_number' | 'feature_code' | 'pattern' | 'unassigned'
  numbers: string[]
  patterns: string[]
  flags: string[]
  modules: string[]
  root_module: string | null
  node_count: number
  max_depth: number
  feature_code: { name: string | null; number: string | null } | null
  flow: CallflowNode | null
  linked_extension: {
    id: string
    display_name: string | null
    extension: string | null
  } | null
  phone_numbers: Array<{ id: string; number: string; state: string | null }>
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}

export type CallflowFilters = {
  search: string
  type: '' | Callflow['route_type']
  module: string
}

export type SyncRun = {
  id: string
  resource_type: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
