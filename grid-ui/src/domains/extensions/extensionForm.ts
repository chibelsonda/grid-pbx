import type {
  ExtensionCredentialsInput,
  ExtensionCredentialsProfile,
  ExtensionAdvancedCallingConfiguration,
  ExtensionCallRecording,
  ExtensionCallerIdSelection,
  ExtensionHotdeskInput,
  ExtensionHotdeskProfile,
  ExtensionUserConfiguration,
  ExtensionFormOptions,
} from './types/extension'

export function defaultExtensionFormOptions(): ExtensionFormOptions {
  return {
    account_defaults: { timezone: null },
    timezones: [],
    languages: [
      { value: 'en-US', label: 'English (United States)' },
      { value: 'fr-FR', label: 'French (France)' },
      { value: 'de-DE', label: 'German (Germany)' },
      { value: 'ru-RU', label: 'Russian (Russia)' },
      { value: 'es-ES', label: 'Spanish (Spain)' },
    ],
    presence_ids: [],
    starter_device: {
      supported_types: ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'],
      provisionable_types: ['sip_device', 'fax', 'ata'],
      sip_credential_types: ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'],
    },
    caller_id_numbers: [],
    media: [],
    restrictions: [],
    metaflow_resources: { media: [], callflows: [], devices: [], extensions: [] },
  }
}

export function defaultExtensionCredentialsInput(): ExtensionCredentialsInput {
  return {
    username: null,
    password: null,
    password_confirmation: null,
    require_password_update: false,
    clear_credentials: false,
  }
}

export function hydrateExtensionCredentialsInput(
  username: string | null,
  source?: Partial<ExtensionCredentialsProfile>,
): ExtensionCredentialsInput {
  return {
    ...defaultExtensionCredentialsInput(),
    username,
    require_password_update: source?.require_password_update ?? false,
  }
}

export function defaultExtensionUserConfiguration(): ExtensionUserConfiguration {
  return {
    language: null,
    presence_id: null,
    call_waiting: { enabled: true },
    do_not_disturb: { enabled: false },
    contact_list: { exclude: false },
    caller_id_options: { outbound_privacy: 'none' },
  }
}

export function defaultExtensionHotdeskInput(): ExtensionHotdeskInput {
  return {
    enabled: false,
    id: null,
    keep_logged_in_elsewhere: false,
    require_pin: false,
    pin: null,
    clear_pin: false,
  }
}

export function hydrateExtensionHotdeskInput(
  source?: Partial<ExtensionHotdeskProfile>,
): ExtensionHotdeskInput {
  const defaults = defaultExtensionHotdeskInput()

  return {
    ...defaults,
    enabled: source?.enabled ?? defaults.enabled,
    id: source?.id ?? defaults.id,
    keep_logged_in_elsewhere: source?.keep_logged_in_elsewhere ?? defaults.keep_logged_in_elsewhere,
    require_pin: source?.require_pin ?? defaults.require_pin,
  }
}

export function hydrateExtensionUserConfiguration(
  source?: Partial<ExtensionUserConfiguration>,
): ExtensionUserConfiguration {
  const defaults = defaultExtensionUserConfiguration()

  return {
    ...defaults,
    ...source,
    call_waiting: { ...defaults.call_waiting, ...source?.call_waiting },
    do_not_disturb: { ...defaults.do_not_disturb, ...source?.do_not_disturb },
    contact_list: { ...defaults.contact_list, ...source?.contact_list },
    caller_id_options: { ...defaults.caller_id_options, ...source?.caller_id_options },
  }
}

function defaultRecordingParameters() {
  return {
    enabled: false,
    format: 'mp3' as const,
    record_min_sec: null,
    record_on_answer: false,
    record_on_bridge: false,
    record_sample_rate: null,
    time_limit: null,
  }
}

export function hydrateExtensionCallRecording(
  source?: Partial<ExtensionCallRecording>,
): ExtensionCallRecording {
  const recording = {} as ExtensionCallRecording

  for (const target of ['account', 'endpoint'] as const) {
    recording[target] = {} as ExtensionCallRecording[typeof target]
    for (const direction of ['any', 'inbound', 'outbound'] as const) {
      recording[target][direction] = {} as ExtensionCallRecording[typeof target][typeof direction]
      for (const network of ['any', 'onnet', 'offnet'] as const) {
        recording[target][direction][network] = {
          ...defaultRecordingParameters(),
          ...source?.[target]?.[direction]?.[network],
        }
      }
    }
  }

  return recording
}

export function hydrateExtensionAdvancedCalling(
  source: ExtensionAdvancedCallingConfiguration,
  restrictionKeys: string[],
) {
  const callerSelection = (
    value: ExtensionAdvancedCallingConfiguration['caller_id']['external'],
  ): ExtensionCallerIdSelection => ({
    name: value.name,
    phone_number_id: value.phone_number_id,
    preserve_number: value.unresolved,
  })

  return {
    caller_id: {
      internal: {
        name: source.caller_id.internal.name,
        number: source.caller_id.internal.number,
      },
      external: callerSelection(source.caller_id.external),
      emergency: callerSelection(source.caller_id.emergency),
    },
    call_forward: { ...source.call_forward },
    call_restriction: Object.fromEntries(
      [...new Set([...restrictionKeys, ...Object.keys(source.call_restriction)])].map((key) => [
        key,
        { action: source.call_restriction[key]?.action ?? ('inherit' as const) },
      ]),
    ),
    call_recording: hydrateExtensionCallRecording(source.call_recording),
    media: {
      ...source.media,
      audio: { codecs: [...source.media.audio.codecs] },
      video: { codecs: [...source.media.video.codecs] },
      encryption: {
        ...source.media.encryption,
        methods: [...source.media.encryption.methods],
      },
    },
    music_on_hold: {
      media_id: source.music_on_hold.media_id,
      preserve_media: source.music_on_hold.unresolved,
    },
    ringtones: { ...source.ringtones },
    dial_plan: {
      system: [...source.dial_plan.system],
      rules: source.dial_plan.rules.map((rule) => ({ ...rule })),
    },
    formatters: source.formatters.map((formatter) => ({ ...formatter })),
    profile: {
      ...source.profile,
      addresses: source.profile.addresses.map((address) => ({
        ...address,
        types: [...address.types],
      })),
      nicknames: [...source.profile.nicknames],
    },
    pronounced_name: {
      media_id: source.pronounced_name.media_id,
      preserve_media: source.pronounced_name.unresolved,
    },
  }
}

export function defaultExtensionAdvancedCallingConfiguration(): ExtensionAdvancedCallingConfiguration {
  return {
    caller_id: {
      internal: { name: null, number: null },
      external: { name: null, phone_number_id: null, number: null, unresolved: false },
      emergency: { name: null, phone_number_id: null, number: null, unresolved: false },
    },
    call_forward: {
      enabled: false,
      number: null,
      direct_calls_only: false,
      failover: false,
      ignore_early_media: true,
      keep_caller_id: true,
      require_keypress: true,
      substitute: true,
    },
    call_restriction: {},
    call_recording: hydrateExtensionCallRecording(),
    media: {
      audio: { codecs: [] },
      video: { codecs: [] },
      bypass_media: false,
      encryption: { enforce_security: false, methods: [] },
      fax_option: false,
      ignore_early_media: false,
      progress_timeout: null,
    },
    music_on_hold: { media_id: null, configured: false, unresolved: false },
    ringtones: { internal: null, external: null },
    dial_plan: { system: [], rules: [] },
    formatters: [],
    profile: {
      addresses: [],
      assistant: null,
      birthday: null,
      nicknames: [],
      note: null,
      role: null,
      sort_string: null,
      title: null,
    },
    pronounced_name: { media_id: null, configured: false, unresolved: false },
  }
}
