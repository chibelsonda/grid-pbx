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
  drop_capability?: CallflowDropCapability
  children: Record<string, CallflowNode>
}

export type CallflowDropCapability = {
  accepts_children: boolean
  default_branch_available: boolean
  branch_mode:
    | 'continuation'
    | 'menu'
    | 'condition'
    | 'temporal'
    | 'priority'
    | 'captured_number'
    | 'terminal'
    | 'locked'
  reason: string | null
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

export type CallflowTreeNodeDeleteInput = {
  node_path: string[]
  confirm_subtree: true
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
  'acdc_queue',
  'hotdesk',
  'do_not_disturb',
  'call_forward',
  'dynamic_cid',
  'pivot',
  'webhook',
  'disa',
  'offnet',
  'resources',
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
    | 'static'
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
  strategy?: 'simultaneous' | 'single' | 'weighted_random'
  endpoints?: CallflowRingGroupEndpoint[]
  repeats?: number
  ignore_forward?: boolean
  fail_on_single_reject?: boolean
  ringback_media_id?: string | null
  ringtone_internal?: string | null
  ringtone_external?: string | null
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
  phone_number_id?: string
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
  queue_id?: string
  id?: string | null
  endpoint_id?: string
  access_policy_id?: string
  route_profile_id?: string
  method?: 'get' | 'post'
  req_format?: 'switch' | 'twiml'
  http_verb?: 'get' | 'post'
  retries?: number
  custom_data?: Record<string, string | number | boolean>
  skip_module: boolean
}

export type CallflowAlertRecipient = {
  type: 'user' | 'email'
  id: string
}

export type CallflowRingGroupEndpoint = {
  device_id?: string
  extension_id?: string
  group_id?: string
  delay: number
  timeout: number
  weight?: number
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
  placement?: CallflowNodePlacement
  confirm_replace?: boolean
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
  placement?: CallflowNodePlacement
  preset?: Readonly<Partial<CallflowInlineNodeData>>
}

export type CallflowNodePlacement = 'append' | 'insert_before' | 'replace'

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
  supports_ring_group_toggle?: boolean
  supports_ringback?: boolean
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
  action_capabilities?: Record<string, CallflowActionCapability>
  pivot_endpoints?: CallflowPivotEndpoint[]
  webhook_endpoints?: CallflowWebhookEndpoint[]
  disa_access_policies?: CallflowDisaAccessPolicy[]
  disa_operational_safety?: CallflowDisaOperationalSafety
  carrier_routes?: CallflowCarrierRoute[]
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
  requires_entry_number?: boolean
  phone_numbers: Array<{
    id: string
    number: string
    state: string | null
    selected: boolean
    available: boolean
    assigned_callflow: { id: string; name: string | null } | null
  }>
  phone_number_inventory?: {
    status: 'healthy' | 'syncing' | 'stale' | 'error'
    last_successful_at: string | null
    error_message: string | null
    total_count: number
    unassigned_count: number
  }
  extension_numbers?: string[]
  preserved_numbers?: string[]
}

export type CallflowExtensionDirectoryEntry = {
  number: string
  source: 'managed_extension' | 'callflow'
  label: string
  callflow: { id: string; name: string | null } | null
  current: boolean
}

export type CallflowExtensionAvailability = {
  number: string
  available: boolean
  reason: string | null
  conflict: {
    source: 'managed_extension' | 'callflow'
    label: string
    callflow: { id: string; name: string | null } | null
  } | null
  suggested_extension: string | null
}

export type CallflowActionCapability = {
  enabled: boolean
  reason: string | null
}

export type CallflowDisaOperationalSafety = {
  ready: boolean
  adapter: string
  ingress_guard_available: boolean
  persistent_lockout_available: boolean
  rate_limit_available: boolean
  concurrency_limit_available: boolean
  destination_policy_available: boolean
  redacted_monitoring_available: boolean
  emergency_stop_available: boolean
  emergency_stop_active: boolean
  reason: string | null
}

export type CallflowPivotEndpoint = {
  id: string
  label: string
  methods: Array<'get' | 'post'>
  formats: Array<'switch' | 'twiml'>
}

export type CallflowWebhookEndpoint = {
  id: string
  label: string
  methods: Array<'get' | 'post'>
  max_retries: number
}

export type CallflowDisaAccessPolicy = {
  id: string
  label: string
  retries: number
  interdigit_ms: number
  max_digits: number
  preconnect_audio: 'dialtone' | 'ringing'
}

export type CallflowCarrierRoute = {
  id: string
  label: string
  module: 'offnet' | 'resources'
  scope: 'global' | 'account' | 'reseller'
}

export type CallflowUpdate = {
  name: string
  destination_type: CallflowDestinationType
  destination_id: string | null
  temporal_rule_ids?: string[]
  temporal_rule_routes?: CallflowTemporalRuleRouteInput[]
  phone_number_ids: string[]
  extension_numbers?: string[]
  manage_fallback?: boolean
  fallback_destination_type?: CallflowDestinationType | null
  fallback_destination_id?: string | null
  manage_menu_branches?: boolean
  menu_branches?: CallflowMenuBranchInput[]
  manage_temporal_match?: boolean
  temporal_match_destination_type?: CallflowDestinationType | null
  temporal_match_destination_id?: string | null
}

export type CallflowEntryPointsUpdate = {
  phone_number_ids: string[]
  extension_numbers: string[]
}

export const callflowInlineRootModules = [
  'ring_group',
  'call_forward',
  'dynamic_cid',
  'pivot',
] as const
export type CallflowInlineRootModule = (typeof callflowInlineRootModules)[number]

export type CallflowInlineRootAction = {
  module: CallflowInlineRootModule
  data: CallflowInlineNodeData
}

export type CallflowInlineRootCreateInput = Omit<
  CallflowUpdate,
  'destination_type' | 'destination_id'
> & {
  destination_type: null
  destination_id: null
  root_action: CallflowInlineRootAction
}

export type CallflowCreateInput = CallflowUpdate | CallflowInlineRootCreateInput

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
