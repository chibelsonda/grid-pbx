import type { ListboxOptionValue } from '@/shared/components/FormListbox.vue'
import type { MetaflowModule, MetaflowNode } from './types'

export type MetaflowFieldDefinition = {
  key: string
  label: string
  type: 'text' | 'number' | 'boolean' | 'select' | 'resource'
  resource?: 'media' | 'callflow' | 'device' | 'extension'
  options?: ListboxOptionValue[]
  min?: number
  max?: number
}

export type MetaflowModuleDefinition = {
  value: MetaflowModule
  label: string
  description: string
  defaults: MetaflowNode['data']
  fields: MetaflowFieldDefinition[]
}

const options = (...values: string[]): ListboxOptionValue[] =>
  values.map((value) => ({ value, label: value.replaceAll('_', ' ') }))

export const metaflowModules: MetaflowModuleDefinition[] = [
  {
    value: 'audio_level',
    label: 'Audio level',
    description: 'Adjust audio gain on a call leg',
    defaults: { action: 'start', level: 0, mode: 'read' },
    fields: [
      { key: 'action', label: 'Action', type: 'select', options: options('start', 'stop') },
      { key: 'level', label: 'Level', type: 'number', min: -4, max: 4 },
      { key: 'mode', label: 'Mode', type: 'select', options: options('read', 'write') },
    ],
  },
  {
    value: 'break',
    label: 'Break',
    description: 'Stop the current metaflow sequence',
    defaults: {},
    fields: [],
  },
  {
    value: 'callflow',
    label: 'Run callflow',
    description: 'Execute a projected callflow',
    defaults: { callflow_id: null },
    fields: [
      { key: 'callflow_id', label: 'Callflow', type: 'resource', resource: 'callflow' },
      { key: 'captures', label: 'Captured digits', type: 'text' },
      { key: 'collected', label: 'Collected digits', type: 'text' },
    ],
  },
  {
    value: 'hangup',
    label: 'Hang up',
    description: 'Terminate the call',
    defaults: {},
    fields: [],
  },
  {
    value: 'hold_control',
    label: 'Hold control',
    description: 'Hold, unhold, or toggle the call',
    defaults: { action: 'toggle' },
    fields: [
      {
        key: 'action',
        label: 'Action',
        type: 'select',
        options: options('hold', 'unhold', 'toggle'),
      },
    ],
  },
  {
    value: 'move',
    label: 'Move call',
    description: 'Move the call to a projected endpoint or extension',
    defaults: {
      device_id: null,
      auto_answer: false,
      can_call_self: true,
      dial_strategy: 'simultaneous',
    },
    fields: [
      { key: 'device_id', label: 'Destination device', type: 'resource', resource: 'device' },
      {
        key: 'extension_id',
        label: 'Destination extension',
        type: 'resource',
        resource: 'extension',
      },
      { key: 'dial_strategy', label: 'Dial strategy', type: 'text' },
      { key: 'auto_answer', label: 'Auto answer', type: 'boolean' },
      { key: 'can_call_self', label: 'Allow same-user devices', type: 'boolean' },
    ],
  },
  {
    value: 'play',
    label: 'Play media',
    description: 'Play projected media to a call leg',
    defaults: { media_id: null, leg: 'both' },
    fields: [
      { key: 'media_id', label: 'Media', type: 'resource', resource: 'media' },
      { key: 'leg', label: 'Call leg', type: 'select', options: options('self', 'peer', 'both') },
    ],
  },
  {
    value: 'record_call',
    label: 'Record call',
    description: 'Control recording without an external upload URL',
    defaults: { action: 'toggle', format: 'mp3', record_on_answer: false, record_on_bridge: false },
    fields: [
      {
        key: 'action',
        label: 'Action',
        type: 'select',
        options: options('mask', 'unmask', 'start', 'stop', 'toggle'),
      },
      { key: 'format', label: 'Format', type: 'select', options: options('mp3', 'wav') },
      { key: 'label', label: 'Label', type: 'text' },
      { key: 'media_name', label: 'Media name', type: 'text' },
      { key: 'record_min_sec', label: 'Minimum seconds', type: 'number', min: 0, max: 3600 },
      { key: 'time_limit', label: 'Time limit', type: 'number', min: 5, max: 10800 },
      { key: 'record_on_answer', label: 'Record on answer', type: 'boolean' },
      { key: 'record_on_bridge', label: 'Record on bridge', type: 'boolean' },
    ],
  },
  {
    value: 'resume',
    label: 'Resume',
    description: 'Resume a paused metaflow sequence',
    defaults: {},
    fields: [],
  },
  {
    value: 'say',
    label: 'Say',
    description: 'Speak structured text to the caller',
    defaults: { text: '', type: 'telephone_number', method: 'pronounced', gender: 'neuter' },
    fields: [
      { key: 'text', label: 'Text', type: 'text' },
      {
        key: 'type',
        label: 'Text type',
        type: 'select',
        options: options(
          'telephone_number',
          'telephone_extension',
          'number',
          'name_spelled',
          'email_address',
          'url',
        ),
      },
      {
        key: 'method',
        label: 'Method',
        type: 'select',
        options: options('pronounced', 'iterated', 'counted'),
      },
      {
        key: 'gender',
        label: 'Voice gender',
        type: 'select',
        options: options('feminine', 'masculine', 'neuter'),
      },
      { key: 'language', label: 'Language', type: 'text' },
    ],
  },
  {
    value: 'sound_touch',
    label: 'Sound touch',
    description: 'Adjust pitch, rate, or tempo',
    defaults: {
      action: 'start',
      pitch: 1,
      rate: 1,
      tempo: 1,
      hook_dtmf: false,
      sending_leg: false,
    },
    fields: [
      { key: 'action', label: 'Action', type: 'select', options: options('start', 'stop') },
      { key: 'pitch', label: 'Pitch', type: 'number', min: 1 },
      { key: 'rate', label: 'Rate', type: 'number', min: 1 },
      { key: 'tempo', label: 'Tempo', type: 'number', min: 1 },
      { key: 'adjust_in_octaves', label: 'Octaves', type: 'number', min: -1, max: 1 },
      { key: 'adjust_in_semitones', label: 'Semitones', type: 'number', min: -14, max: 14 },
      { key: 'hook_dtmf', label: 'Enable DTMF control', type: 'boolean' },
      { key: 'sending_leg', label: 'Apply to sending leg', type: 'boolean' },
    ],
  },
  {
    value: 'transfer',
    label: 'Transfer',
    description: 'Transfer the selected call leg',
    defaults: { target: '', transfer_type: 'blind', leg: 'self' },
    fields: [
      { key: 'target', label: 'Target extension or DID', type: 'text' },
      {
        key: 'transfer_type',
        label: 'Transfer type',
        type: 'select',
        options: options('blind', 'attended'),
      },
      { key: 'leg', label: 'Call leg', type: 'select', options: options('self', 'peer', 'both') },
    ],
  },
  {
    value: 'tts',
    label: 'Text to speech',
    description: 'Speak free-form text',
    defaults: { engine: 'flite', text: '', voice: 'female', leg: 'self' },
    fields: [
      { key: 'text', label: 'Text', type: 'text' },
      { key: 'engine', label: 'Engine', type: 'text' },
      { key: 'voice', label: 'Voice', type: 'text' },
      { key: 'language', label: 'Language', type: 'text' },
      { key: 'leg', label: 'Call leg', type: 'select', options: options('self', 'peer', 'both') },
    ],
  },
]

export const metaflowModuleOptions = metaflowModules.map(({ value, label, description }) => ({
  value,
  label,
  description,
}))

export function metaflowDefinition(module: MetaflowModule): MetaflowModuleDefinition {
  return metaflowModules.find((item) => item.value === module) ?? metaflowModules[0]!
}

export function newMetaflowNode(module: MetaflowModule = 'transfer'): MetaflowNode {
  return { module, data: { ...metaflowDefinition(module).defaults }, children: [] }
}
