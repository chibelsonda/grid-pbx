export type MetaflowModule =
  | 'audio_level'
  | 'break'
  | 'callflow'
  | 'hangup'
  | 'hold_control'
  | 'move'
  | 'play'
  | 'record_call'
  | 'resume'
  | 'say'
  | 'sound_touch'
  | 'transfer'
  | 'tts'

export type MetaflowNode = {
  module: MetaflowModule
  data: Record<string, string | number | boolean | null>
  children: MetaflowChild[]
}

export type MetaflowChild = MetaflowNode & { key: string }
export type MetaflowAction = MetaflowNode & {
  trigger_type: 'number' | 'pattern'
  trigger: string
}

export type MetaflowExtensionOption = {
  id: string
  display_name: string
  extension: string | null
}

export type MetaflowResources = {
  media: Array<{ id: string; name: string | null }>
  callflows: Array<{ id: string; name: string | null; description: string | null }>
  devices: Array<{ id: string; name: string | null }>
  extensions: MetaflowExtensionOption[]
}
