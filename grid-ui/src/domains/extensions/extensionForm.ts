import type {
  ExtensionCredentialsInput,
  ExtensionCredentialsProfile,
  ExtensionHotdeskInput,
  ExtensionHotdeskProfile,
  ExtensionUserConfiguration,
} from './types/extension'

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
