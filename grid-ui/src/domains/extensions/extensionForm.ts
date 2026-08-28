import type { ExtensionUserConfiguration } from './types/extension'

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
