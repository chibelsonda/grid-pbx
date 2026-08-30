import { callflowActionCatalog } from './callflowActionCatalog'

export type CallflowActionAppearance = {
  nodeBorder: string
  nodeIcon: string
  paletteBorder: string
  paletteIcon: string
}

const categoryByModule = new Map(
  callflowActionCatalog.flatMap((category) =>
    category.actions.map((action) => [action.module, category.id] as const),
  ),
)

// Palette taxonomy follows Switch, while node accents remain semantic so changing
// the visible registry grouping cannot unexpectedly recolor persisted callflows.
for (const module of [
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
]) {
  categoryByModule.set(module, 'routing')
}

for (const module of [
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
]) {
  categoryByModule.set(module, 'media')
}

for (const module of [
  'voicemail',
  'faxbox',
  'fax_detect',
  'receive_fax',
  'missed_call_alert',
]) {
  categoryByModule.set(module, 'messaging')
}

for (const module of [
  'check_cid',
  'cidlistmatch',
  'dynamic_cid',
  'lookupcidname',
  'prepend_cid',
  'privacy',
  'set_cid',
  'set_alert_info',
  'nomorobo',
]) {
  categoryByModule.set(module, 'identity')
}

for (const module of [
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
]) {
  categoryByModule.set(module, 'features')
}

const advancedAppearance: CallflowActionAppearance = {
  nodeBorder: 'border-slate-400 hover:border-slate-500',
  nodeIcon: 'text-slate-200',
  paletteBorder: 'border-slate-300 enabled:hover:border-slate-500 enabled:hover:bg-slate-100',
  paletteIcon: 'text-slate-200',
}

const appearances: Record<string, CallflowActionAppearance> = {
  routing: {
    nodeBorder: 'border-blue-300 hover:border-blue-400',
    nodeIcon: 'text-blue-300',
    paletteBorder: 'border-blue-200 enabled:hover:border-blue-400 enabled:hover:bg-blue-50',
    paletteIcon: 'text-blue-300',
  },
  media: {
    nodeBorder: 'border-violet-300 hover:border-violet-400',
    nodeIcon: 'text-violet-300',
    paletteBorder: 'border-violet-200 enabled:hover:border-violet-400 enabled:hover:bg-violet-50',
    paletteIcon: 'text-violet-300',
  },
  messaging: {
    nodeBorder: 'border-cyan-300 hover:border-cyan-400',
    nodeIcon: 'text-cyan-300',
    paletteBorder: 'border-cyan-200 enabled:hover:border-cyan-400 enabled:hover:bg-cyan-50',
    paletteIcon: 'text-cyan-300',
  },
  identity: {
    nodeBorder: 'border-amber-300 hover:border-amber-400',
    nodeIcon: 'text-amber-300',
    paletteBorder: 'border-amber-200 enabled:hover:border-amber-400 enabled:hover:bg-amber-50',
    paletteIcon: 'text-amber-300',
  },
  features: {
    nodeBorder: 'border-emerald-300 hover:border-emerald-400',
    nodeIcon: 'text-emerald-300',
    paletteBorder:
      'border-emerald-200 enabled:hover:border-emerald-400 enabled:hover:bg-emerald-50',
    paletteIcon: 'text-emerald-300',
  },
  advanced: advancedAppearance,
}

const unresolvedAppearance: CallflowActionAppearance = {
  nodeBorder: 'border-rose-300 hover:border-rose-400',
  nodeIcon: 'text-rose-300',
  paletteBorder: 'border-rose-200 enabled:hover:border-rose-400 enabled:hover:bg-rose-50',
  paletteIcon: 'text-rose-300',
}

export function callflowActionAppearance(
  module: string,
  unresolved = false,
): CallflowActionAppearance {
  if (unresolved) return unresolvedAppearance

  return appearances[categoryByModule.get(module) ?? 'advanced'] ?? advancedAppearance
}
