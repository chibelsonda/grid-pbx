import {
  callflowInlineModules,
  type CallflowDestinationType,
  type CallflowInlineModule,
} from '../types/callRouting'

export type CallflowActionStatus = 'guided' | 'planned' | 'restricted'

export type CallflowAction = {
  module: string
  label: string
  description: string
  status: CallflowActionStatus
}

export type CallflowActionCategory = {
  id: string
  label: string
  description: string
  actions: CallflowAction[]
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
  'resources',
  'webhook',
])

const descriptions: Record<string, string> = {
  acdc_agent: 'Manage an agent state inside a queue flow.',
  acdc_member: 'Send the caller to a queue as a member.',
  acdc_queue: 'Enter a configured call-center queue.',
  callflow: 'Continue execution in another callflow.',
  collect_dtmf: 'Collect keypad input before continuing.',
  conference: 'Join a configured conference.',
  device: 'Ring one projected endpoint.',
  directory: 'Open a configured dial-by-name directory.',
  dead_air: 'Suppress media and wait until the caller hangs up.',
  disa: 'Provide authenticated direct inward system access.',
  faxbox: 'Deliver a fax to a configured fax box.',
  flush_dtmf: 'Clear a named collection of buffered keypad digits.',
  group: 'Ring a configured group of endpoints.',
  menu: 'Route input through a configured IVR menu.',
  missed_call_alert: 'Notify extensions or email addresses about a missed call.',
  offnet: 'Send a call through an external carrier resource.',
  pivot: 'Delegate call control to an external application.',
  play: 'Play projected media to the caller.',
  record_call: 'Record the active call according to policy.',
  send_dtmf: 'Send configured keypad digits to the active call.',
  resources: 'Select carrier resources for external routing.',
  temporal_route: 'Branch using a business-hours rule set.',
  language: 'Change the call language for subsequent prompts.',
  tts: 'Generate speech from configured text.',
  user: 'Ring the devices assigned to an extension.',
  voicemail: 'Send the caller to a voicemail box.',
  webhook: 'Notify an external HTTPS endpoint during the call.',
}

function label(module: string): string {
  const overrides: Record<string, string> = {
    acdc_agent: 'Queue agent',
    acdc_member: 'Queue member',
    acdc_queue: 'Queue',
    cidlistmatch: 'Caller ID list match',
    disa: 'DISA',
    edr: 'Event data record',
    tts: 'Text to speech',
  }

  return (
    overrides[module] ??
    module.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
  )
}

function action(module: string): CallflowAction {
  return {
    module,
    label: label(module),
    description:
      descriptions[module] ?? `Configure the Switch ${label(module).toLowerCase()} action.`,
    status: guidedModules.has(module)
      ? 'guided'
      : restrictedModules.has(module)
        ? 'restricted'
        : 'planned',
  }
}

function category(
  id: string,
  label: string,
  description: string,
  modules: string[],
): CallflowActionCategory {
  return { id, label, description, actions: modules.map(action) }
}

// This catalog mirrors the primary callflow schemas shipped in src/kazoo. Helper-only
// schemas (audio_macro prompt/say/tone and skel) intentionally are not standalone actions.
export const callflowActionCatalog: CallflowActionCategory[] = [
  category('routing', 'Routing and endpoints', 'Connect calls to people, endpoints, and routes.', [
    'user',
    'device',
    'callflow',
    'group',
    'ring_group',
    'page_group',
    'directory',
    'menu',
    'conference',
    'acdc_member',
    'acdc_queue',
    'acdc_agent',
    'acdc_wait_time',
    'transfer',
    'route_to_cid',
    'branch_bnumber',
    'branch_variable',
    'offnet',
    'resources',
  ]),
  category('media', 'Media and caller input', 'Play, capture, and transform in-call media.', [
    'play',
    'tts',
    'audio_macro',
    'collect_dtmf',
    'send_dtmf',
    'flush_dtmf',
    'dead_air',
    'sleep',
    'language',
    'record_call',
    'record_caller',
  ]),
  category(
    'messaging',
    'Messaging and delivery',
    'Handle voicemail, fax, and call notifications.',
    ['voicemail', 'faxbox', 'fax_detect', 'receive_fax', 'missed_call_alert'],
  ),
  category('identity', 'Caller identity and screening', 'Evaluate or change caller identity.', [
    'check_cid',
    'cidlistmatch',
    'dynamic_cid',
    'lookupcidname',
    'prepend_cid',
    'privacy',
    'set_cid',
    'set_alert_info',
    'nomorobo',
  ]),
  category(
    'features',
    'Time, presence, and features',
    'Apply schedules and telephony feature state.',
    [
      'temporal_route',
      'manual_presence',
      'do_not_disturb',
      'call_forward',
      'call_waiting',
      'hotdesk',
      'camping_feature',
      'park',
      'move',
      'group_pickup',
      'group_pickup_feature',
      'ring_group_toggle',
      'conference_feature',
      'intercom',
    ],
  ),
  category(
    'advanced',
    'Advanced and administration',
    'Low-level control and external integrations.',
    [
      'after_bridge',
      'action',
      'disa',
      'eavesdrop',
      'eavesdrop_feature',
      'intercept',
      'intercept_feature',
      'pivot',
      'webhook',
      'response',
      'set',
      'set_variable',
      'set_variables',
      'edr',
      'hangup',
    ],
  ),
]

export function findCallflowAction(module: string): CallflowAction | null {
  return (
    callflowActionCatalog
      .flatMap((catalogCategory) => catalogCategory.actions)
      .find((catalogAction) => catalogAction.module === module) ?? null
  )
}

export function callflowActionDestinationType(module: string): CallflowDestinationType | null {
  return guidedDestinationTypes[module] ?? null
}

export function isGuidedInlineCallflowModule(module: string): module is CallflowInlineModule {
  return callflowInlineModules.some((inlineModule) => inlineModule === module)
}
