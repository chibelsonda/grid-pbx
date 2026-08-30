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
    kind: 'default' | 'schedule_match' | 'condition' | 'key' | 'preserved'
  } | null
  temporal_rules?: CallflowTemporalRuleOption[]
  settings?: Record<string, unknown> | null
  children: Record<string, CallflowNode>
}

export type CallflowNodeSelection = {
  node: CallflowNode
  path: string[]
}

const fixedCallflowTreeBranchKeys = [
  '_',
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
  'rule_set',
  'match',
  'nomatch',
] as const

export type CallflowPriorityBranchKey = `${number}`
export type CallflowCapturedNumberBranchKey = string & {
  readonly __callflowCapturedNumberBranchKey: unique symbol
}
export type CallflowTreeBranchKey =
  | (typeof fixedCallflowTreeBranchKeys)[number]
  | CallflowPriorityBranchKey
  | CallflowCapturedNumberBranchKey

export const callflowPriorityBranchKeys = Array.from(
  { length: 256 },
  (_, priority) => String(priority) as CallflowPriorityBranchKey,
)

export const callflowTreeBranchKeys: readonly CallflowTreeBranchKey[] = [
  ...fixedCallflowTreeBranchKeys,
  ...callflowPriorityBranchKeys.slice(10),
]

export function isCallflowTreeBranchKey(value: unknown): value is CallflowTreeBranchKey {
  return (
    typeof value === 'string' &&
    (callflowTreeBranchKeys.includes(value as CallflowTreeBranchKey) ||
      isCallflowCapturedNumberBranchKey(value))
  )
}

export function isCallflowCapturedNumberBranchKey(
  value: unknown,
): value is CallflowCapturedNumberBranchKey {
  return typeof value === 'string' && /^[0-9*#+]{1,64}$/.test(value)
}

export type CallflowTreeMoveInput = {
  source_path: string[]
  destination_parent_path: string[]
  destination_branch: CallflowTreeBranchKey
}

export type CallflowTreeReorderInput = {
  mode: 'insert_before' | 'swap'
  source_path: string[]
  target_path: string[]
}

export type CallflowTreeNodeCreateInput = {
  parent_path: string[]
  branch: CallflowTreeBranchKey
  destination_type: CallflowDestinationType
  destination_id: string
}

export type CallflowTreeNodeUpdateInput = {
  node_path: string[]
  destination_type: CallflowDestinationType
  destination_id: string
}

export const callflowInlineModules = [
  'sleep',
  'tts',
  'collect_dtmf',
  'record_call',
  'record_caller',
  'send_dtmf',
  'flush_dtmf',
  'dead_air',
  'language',
  'response',
  'hangup',
  'set_variable',
  'set_variables',
  'manual_presence',
  'group_pickup',
  'page_group',
  'ring_group',
  'receive_fax',
  'conference',
  'voicemail',
  'branch_variable',
  'branch_bnumber',
  'missed_call_alert',
  'set_cid',
  'prepend_cid',
  'set_alert_info',
  'check_cid',
  'cidlistmatch',
  'temporal_route',
  'ring_group_toggle',
  'hotdesk',
  'do_not_disturb',
  'call_forward',
] as const

export type CallflowInlineModule = (typeof callflowInlineModules)[number]

export type CallflowInlineNodeData = {
  duration?: number
  unit?: 'ms' | 's' | 'm' | 'h'
  text?: string
  voice?: string | null
  language?: string | null
  engine?: 'flite' | 'google' | 'ispeech' | 'voicefabric' | null
  endless_playback?: boolean
  collection_name?: string | null
  interdigit_timeout?: number
  max_digits?: number
  terminators?: string[]
  action?:
    | 'start'
    | 'stop'
    | 'reset'
    | 'prepend'
    | 'enable'
    | 'disable'
    | 'login'
    | 'logout'
    | 'toggle'
    | 'activate'
    | 'deactivate'
    | 'update'
    | 'check'
  format?: 'mp3' | 'wav' | null
  label?: string | null
  record_min_sec?: number | null
  record_on_answer?: boolean
  record_on_bridge?: boolean
  record_sample_rate?: number | null
  should_follow_transfer?: boolean
  time_limit?: number
  timeout?: number
  digits?: string
  duration_ms?: number
  code?: number
  message?: string | null
  variable?: 'call_priority'
  value?: string
  channel?: 'a' | 'both'
  custom_application_vars?: Record<string, string>
  export?: boolean
  presence_id?: string
  status?: 'idle' | 'ringing' | 'busy'
  target_type?: 'extension' | 'device' | 'group'
  target_id?: string
  audio?: 'one-way' | 'two-way'
  device_ids?: string[]
  strategy?: 'simultaneous' | 'single'
  endpoints?: CallflowRingGroupEndpoint[]
  repeats?: number
  owner_id?: string
  fax_option?: 'auto' | boolean
  service_mode?: true
  scope?: 'custom_channel_vars'
  hunt?: boolean
  hunt_allow?: string | null
  hunt_deny?: string | null
  recipients?: CallflowAlertRecipient[]
  caller_id_name?: string
  caller_id_number?: string
  caller_id_name_prefix?: string
  caller_id_number_prefix?: string
  apply_to?: 'original' | 'current'
  alert_info?: string
  regex?: string
  use_absolute_mode?: false
  external_caller_id_name?: string | null
  external_caller_id_number?: string | null
  user_id?: string | null
  caller_id_list_id?: string
  rules?: string[]
  callflow_id?: string
  id?: string | null
  skip_module: boolean
}

export type CallflowAlertRecipient = {
  type: 'user' | 'email'
  id: string
}

export type CallflowRingGroupEndpoint = {
  device_id: string
  delay: number
  timeout: number
}

export type CallflowCustomApplicationVariable = {
  key: string
  value: string
}

export type CallflowInlineNodeFormData = CallflowInlineNodeData & {
  custom_application_variables?: CallflowCustomApplicationVariable[]
}

export type CallflowInlineNodeCreateInput = {
  parent_path: string[]
  branch: CallflowTreeBranchKey
  module: CallflowInlineModule
  data: CallflowInlineNodeData
}

export type CallflowInlineNodeUpdateInput = {
  node_path: string[]
  module: CallflowInlineModule
  data: CallflowInlineNodeData
}

export type CallflowNodeEditorContext = {
  operation: 'create' | 'update'
  path: string[]
  node: CallflowNode
  module: string
  preset?: Readonly<Partial<CallflowInlineNodeData>>
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
  'temporal_rules',
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

export type CallflowTemporalRuleRouteInput = {
  rule_id: string
  destination_type: CallflowDestinationType
  destination_id: string
}

export type CallflowDirectTemporalRoute = {
  rule_id: string | null
  label: string
  position: number
  resolved: boolean
  editable: boolean
  blocked_reason: string | null
  target: {
    type: CallflowDestinationType
    id: string
    label: string
  } | null
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
  direct_temporal_routes: CallflowDirectTemporalRoute[]
  temporal_rule_sets: Record<string, CallflowTemporalRuleOption[]>
  temporal_rules: CallflowDestination[]
  caller_id_lists: CallflowDestination[]
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
  destination_id: string | null
  temporal_rule_ids?: string[]
  temporal_rule_routes?: CallflowTemporalRuleRouteInput[]
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
