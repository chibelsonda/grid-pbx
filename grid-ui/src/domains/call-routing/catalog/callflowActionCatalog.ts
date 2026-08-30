import {
  callflowInlineModules,
  type CallflowDestinationType,
  type CallflowInlineModule,
  type CallflowInlineNodeData,
  type CallflowNode,
} from '../types/callRouting'

export type CallflowActionStatus = 'guided' | 'planned' | 'restricted'

export type CallflowAction = {
  id: string
  module: string
  label: string
  description: string
  status: CallflowActionStatus
  action?: string
  preset?: Readonly<Partial<CallflowInlineNodeData>>
}

export type CallflowActionCategory = {
  id: string
  label: string
  description: string
  actions: CallflowAction[]
}

type CallflowActionDefinition = {
  module: string
  label?: string
  action?: string
  status?: CallflowActionStatus
}

const guidedDestinationTypes: Partial<Record<string, CallflowDestinationType>> = {
  user: 'extension',
  device: 'device',
  voicemail: 'voicemail',
  callflow: 'callflow',
  play: 'media',
  directory: 'directory',
  group: 'group',
  acdc_member: 'queue',
  menu: 'menu',
  conference: 'conference',
  faxbox: 'fax_box',
  temporal_route: 'temporal_rule_set',
}

const guidedModules = new Set([
  'user',
  'device',
  'voicemail',
  'callflow',
  'play',
  'directory',
  'group',
  'acdc_member',
  'menu',
  'conference',
  'faxbox',
  'temporal_route',
  ...callflowInlineModules,
])

const restrictedModules = new Set([
  'disa',
  'eavesdrop',
  'eavesdrop_feature',
  'intercept',
  'intercept_feature',
  'offnet',
  'pivot',
  'privacy',
  'resources',
  'webhook',
])

const descriptions: Record<string, string> = {
  acdc_agent: 'Manage an agent state inside a queue flow.',
  acdc_member: 'Send the caller to a queue as a member.',
  acdc_queue: 'Enter a configured call-center queue.',
  callflow: 'Continue execution in another callflow.',
  branch_variable: 'Branch by the supported call-priority value with a safe fallback path.',
  check_cid: 'Branch by a safe regular expression matched against incoming caller ID.',
  cidlistmatch: 'Branch when incoming caller ID matches a synchronized Caller-ID List.',
  collect_dtmf: 'Collect keypad input before continuing.',
  conference: 'Join a configured conference.',
  device: 'Ring one projected endpoint.',
  directory: 'Open a configured dial-by-name directory.',
  dead_air: 'Suppress media and wait until the caller hangs up.',
  disa: 'Provide authenticated direct inward system access.',
  faxbox: 'Deliver a fax to a configured fax box.',
  flush_dtmf: 'Clear a named collection of buffered keypad digits.',
  group: 'Ring a configured group of endpoints.',
  hangup: 'End the current callflow path and disconnect the call.',
  menu: 'Route input through a configured IVR menu.',
  missed_call_alert: 'Notify extensions or email addresses about a missed call.',
  offnet: 'Send a call through an external carrier resource.',
  pivot: 'Delegate call control to an external application.',
  play: 'Play projected media to the caller.',
  record_call: 'Change recording state for the active call.',
  prepend_cid: 'Prepend or reset caller ID name and number prefixes.',
  send_dtmf: 'Send configured keypad digits to the active call.',
  set_alert_info: 'Set a distinctive-ring Alert-Info value for the called endpoint.',
  set_cid: 'Replace or restore the current caller ID name and number.',
  set_variable: 'Set the supported call-priority variable on one or both call legs.',
  resources: 'Select carrier resources for external routing.',
  temporal_route: 'Route by a time-of-day rule or change its operational state.',
  language: 'Change the call language for subsequent prompts.',
  response: 'Return a final SIP response code and optional cause text.',
  tts: 'Generate speech from configured text.',
  user: 'Ring the devices assigned to an extension.',
  voicemail: 'Send the caller to a voicemail box.',
  webhook: 'Notify an external HTTPS endpoint during the call.',
}

const moduleLabels: Record<string, string> = {
  acdc_agent: 'ACDC Agent',
  acdc_member: 'ACDC Member',
  acdc_queue: 'ACDC Queue',
  branch_variable: 'Branch by Call Priority',
  check_cid: 'Check CID',
  cidlistmatch: 'Caller ID List Match',
  collect_dtmf: 'Collect DTMF',
  disa: 'DISA',
  dynamic_cid: 'Dynamic cid',
  edr: 'Event Data Record',
  faxbox: 'Fax Boxes',
  group_pickup: 'Group Pickup',
  manual_presence: 'Manual Presence',
  missed_call_alert: 'Missed Call Alert',
  page_group: 'Page Group',
  receive_fax: 'Receive Fax',
  ring_group: 'Ring Group',
  ring_group_toggle: 'Ring Group Toggle',
  set_alert_info: 'Distinctive Ring',
  set_variables: 'Set CAV',
  tts: 'TTS',
}

function humanize(module: string): string {
  return (
    moduleLabels[module] ??
    module.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
  )
}

function definition(
  module: string,
  label?: string,
  action?: string,
  status?: CallflowActionStatus,
): CallflowActionDefinition {
  return { module, label, action, status }
}

function makeAction(input: string | CallflowActionDefinition): CallflowAction {
  const item = typeof input === 'string' ? definition(input) : input
  const actionStatus =
    item.status ??
    (guidedModules.has(item.module)
      ? 'guided'
      : restrictedModules.has(item.module)
        ? 'restricted'
        : 'planned')

  return {
    id: item.action ? `${item.module}[action=${item.action}]` : item.module,
    module: item.module,
    label: item.label ?? humanize(item.module),
    description:
      descriptions[item.module] ??
      `Configure the Switch ${(item.label ?? humanize(item.module)).toLowerCase()} action.`,
    status: actionStatus,
    ...(item.action ? { action: item.action } : {}),
    ...(item.action ? { preset: { action: item.action as CallflowInlineNodeData['action'] } } : {}),
  }
}

function category(
  id: string,
  label: string,
  description: string,
  definitions: Array<string | CallflowActionDefinition>,
): CallflowActionCategory {
  return { id, label, description, actions: definitions.map(makeAction) }
}

// Category names, order, and action labels intentionally mirror the installed
// Switch/Monster Callflows registry. Current Switch schema modules that the legacy
// registry does not expose remain available in the final compatibility category.
export const callflowActionCatalog: CallflowActionCategory[] = [
  category('basic', 'Basic', 'The primary actions shown by the Switch callflow editor.', [
    definition('play', 'Media'),
    definition('ring_group', 'Ring Group'),
    'conference',
    'user',
    'voicemail',
    'menu',
  ]),
  category('advanced', 'Advanced', 'Additional actions shown by the Switch callflow editor.', [
    'device',
    definition('set_alert_info', 'Distinctive Ring'),
    'callflow',
    definition('page_group', 'Page Group'),
    definition('set_variables', 'Set CAV'),
    definition('missed_call_alert', 'Missed Call Alert'),
    definition('manual_presence', 'Manual Presence'),
    definition('tts', 'TTS'),
    'sleep',
    'language',
    definition('group_pickup', 'Group Pickup'),
    definition('receive_fax', 'Receive Fax'),
    'pivot',
    definition('collect_dtmf', 'Collect DTMF'),
    definition('disa', 'DISA'),
    'response',
    definition('conference', 'Conference Service', 'service', 'planned'),
    definition('voicemail', 'Check Voicemail', 'check', 'planned'),
    definition('faxbox', 'Fax Boxes'),
    definition('offnet', 'Global Carrier'),
    definition('resources', 'Account Carrier'),
    'directory',
    'webhook',
  ]),
  category('time-of-day', 'Time of Day', 'Time-of-day routing and operational controls.', [
    definition('temporal_route', 'Time of Day'),
    definition('temporal_route', 'Disable Time of Day', 'disable'),
    definition('temporal_route', 'Enable Time of Day', 'enable'),
    definition('temporal_route', 'Reset Time of Day', 'reset'),
  ]),
  category('ring-group-toggle', 'Ring Group Toggle', 'Ring-group membership controls.', [
    definition('ring_group_toggle', 'Ring Group Login', 'login'),
    definition('ring_group_toggle', 'Ring Group Logout', 'logout'),
  ]),
  category('hotdesking', 'Hotdesking', 'Hot desk session controls.', [
    definition('hotdesk', 'Hot Desk login', 'login'),
    definition('hotdesk', 'Hot Desk logout', 'logout'),
    definition('hotdesk', 'Hot Desk toggle', 'toggle'),
  ]),
  category('do-not-disturb', 'Do Not Disturb', 'Do Not Disturb state controls.', [
    definition('do_not_disturb', 'Activate Do Not Disturb', 'activate'),
    definition('do_not_disturb', 'Deactivate Do Not Disturb', 'deactivate'),
    definition('do_not_disturb', 'Toggle Do Not Disturb', 'toggle'),
  ]),
  category('caller-id', 'Caller-ID', 'Caller-ID collection and prefix controls.', [
    definition('dynamic_cid', 'Dynamic cid'),
    definition('prepend_cid', 'Prepend', 'prepend'),
    definition('prepend_cid', 'Reset Prepend', 'reset'),
  ]),
  category('call-recording', 'Call Recording', 'Active-call recording controls.', [
    definition('record_call', 'Start Call Recording', 'start'),
    definition('record_call', 'Stop Call Recording', 'stop'),
  ]),
  category('call-forwarding', 'Call Forwarding', 'Call-forwarding state controls.', [
    definition('call_forward', 'Enable call forwarding', 'activate'),
    definition('call_forward', 'Disable call forwarding', 'deactivate'),
    definition('call_forward', 'Update call forwarding', 'update'),
  ]),
  category(
    'schema-extensions',
    'Schema extensions',
    'Current Switch schema modules not exposed by the installed legacy palette.',
    [
      'group',
      'acdc_member',
      'acdc_queue',
      'acdc_agent',
      'acdc_wait_time',
      'transfer',
      'route_to_cid',
      'branch_bnumber',
      'branch_variable',
      'audio_macro',
      'send_dtmf',
      'flush_dtmf',
      'dead_air',
      'record_caller',
      'fax_detect',
      'check_cid',
      'cidlistmatch',
      'lookupcidname',
      'privacy',
      'set_cid',
      'nomorobo',
      'call_waiting',
      'camping_feature',
      'park',
      'move',
      'group_pickup_feature',
      'intercom',
      'after_bridge',
      'action',
      'eavesdrop',
      'eavesdrop_feature',
      'intercept',
      'intercept_feature',
      'set',
      'set_variable',
      'edr',
      'hangup',
    ],
  ),
]

export function findCallflowAction(module: string, action?: string): CallflowAction | null {
  const actions = callflowActionCatalog.flatMap((catalogCategory) => catalogCategory.actions)

  if (action) {
    const variant = actions.find(
      (catalogAction) => catalogAction.module === module && catalogAction.action === action,
    )
    if (variant) return variant
  }

  return actions.find((catalogAction) => catalogAction.module === module) ?? null
}

export function findCallflowActionById(id: string): CallflowAction | null {
  return (
    callflowActionCatalog
      .flatMap((catalogCategory) => catalogCategory.actions)
      .find((catalogAction) => catalogAction.id === id) ?? null
  )
}

export function callflowNodeLabel(
  node: Pick<CallflowNode, 'module' | 'settings' | 'target' | 'reference_status'>,
): string {
  const action = typeof node.settings?.action === 'string' ? node.settings.action : undefined

  if (node.module === 'conference' && node.settings?.service_mode === true) {
    return 'Conference Service'
  }

  if (node.module === 'voicemail' && action === 'check') {
    return 'Check Voicemail'
  }

  return findCallflowAction(node.module, action)?.label ?? humanize(node.module)
}

export function callflowActionDestinationType(module: string): CallflowDestinationType | null {
  return guidedDestinationTypes[module] ?? null
}

const operationModules = new Set([
  'temporal_route',
  'ring_group_toggle',
  'hotdesk',
  'do_not_disturb',
  'call_forward',
])

export function isGuidedInlineCallflowModule(
  module: string,
  action?: unknown,
): module is CallflowInlineModule {
  if (!callflowInlineModules.some((inlineModule) => inlineModule === module)) return false
  return !operationModules.has(module) || (typeof action === 'string' && action !== '')
}
