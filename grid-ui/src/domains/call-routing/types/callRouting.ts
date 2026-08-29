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
  branch?: {
    key: string
    label: string
    kind: 'default' | 'schedule_match' | 'key' | 'preserved'
  } | null
  children: Record<string, CallflowNode>
}

export type CallflowNodeSelection = {
  node: CallflowNode
  path: string[]
}

export const callflowDestinationTypes = [
  'extension',
  'device',
  'voicemail',
  'callflow',
  'media',
  'directory',
  'group',
  'queue',
  'menu',
  'conference',
  'fax_box',
  'temporal_rule_set',
] as const

export type CallflowDestinationType = (typeof callflowDestinationTypes)[number]

export type CallflowDestination = {
  id: string
  label: string
  detail: string | null
}

export const callflowMenuBranchKeys = [
  'timeout',
  '0',
  '1',
  '2',
  '3',
  '4',
  '5',
  '6',
  '7',
  '8',
  '9',
  '*',
] as const

export type CallflowMenuBranchKey = (typeof callflowMenuBranchKeys)[number]

export type CallflowMenuBranchInput = {
  key: CallflowMenuBranchKey
  destination_type: CallflowDestinationType
  destination_id: string
}

export type CallflowTemporalRuleOption = {
  id: string | null
  label: string
  position: number
  resolved: boolean
}

export type CallflowEditor = {
  mode: 'create' | 'update'
  editable: boolean
  blocked_reason: string | null
  fallback: {
    editable: boolean
    blocked_reason: string | null
    target: {
      type: CallflowDestinationType
      id: string
      label: string
    } | null
  }
  menu_branches: {
    editable: boolean
    blocked_reason: string | null
    branches: Array<{
      key: CallflowMenuBranchKey
      label: string
      editable: boolean
      blocked_reason: string | null
      target: {
        type: CallflowDestinationType
        id: string
        label: string
      } | null
    }>
    legacy_hash_present: boolean
    unknown_branch_keys: string[]
  }
  temporal_match: {
    editable: boolean
    blocked_reason: string | null
    target: {
      type: CallflowDestinationType
      id: string
      label: string
    } | null
    preserved_branch_count: number
  }
  temporal_rule_sets: Record<string, CallflowTemporalRuleOption[]>
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
  manage_fallback?: boolean
  fallback_destination_type?: CallflowDestinationType | null
  fallback_destination_id?: string | null
  manage_menu_branches?: boolean
  menu_branches?: CallflowMenuBranchInput[]
  manage_temporal_match?: boolean
  temporal_match_destination_type?: CallflowDestinationType | null
  temporal_match_destination_id?: string | null
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
