import type {
  VoicemailBoxBasicForm,
  VoicemailBoxConfiguration,
  VoicemailBoxInput,
  VoicemailFormOptions,
  VoicemailNotificationCallback,
} from './types/voicemail'

export function defaultVoicemailBoxBasicForm(): VoicemailBoxBasicForm {
  return {
    name: '',
    mailbox: '',
    assigned_extension_id: null,
    timezone: null,
    notification_emails: '',
    transcribe: false,
    require_pin: false,
    pin: '',
  }
}

export function defaultVoicemailFormOptions(): VoicemailFormOptions {
  return {
    account_defaults: { timezone: null },
    timezones: [],
    extensions: [],
    capabilities: {
      voicemail_transcription: {
        schema_supported: true,
        runtime_available: null,
        default_enabled: null,
      },
    },
  }
}

export function defaultVoicemailNotificationCallback(): VoicemailNotificationCallback {
  return {
    disabled: false,
    number: null,
    attempts: 3,
    interval_s: 300,
    timeout_s: 30,
    schedule: [],
  }
}

export function defaultVoicemailBoxConfiguration(): VoicemailBoxConfiguration {
  return {
    check_if_owner: true,
    delete_after_notify: false,
    include_message_on_notify: true,
    include_transcription_on_notify: true,
    media_extension: 'mp3',
    not_configurable: false,
    oldest_message_first: false,
    save_after_notify: false,
    skip_envelope: false,
    skip_greeting: false,
    skip_instructions: false,
    is_voicemail_ff_rw_enabled: false,
    seek_duration_ms: 10000,
    notify_callback: null,
  }
}

export function hydrateVoicemailBoxConfiguration(
  source?: Partial<VoicemailBoxConfiguration>,
): VoicemailBoxConfiguration {
  return { ...defaultVoicemailBoxConfiguration(), ...source }
}

export function buildVoicemailBoxInput(
  form: VoicemailBoxBasicForm,
  configuration: VoicemailBoxConfiguration,
  callbackConfigured: boolean,
  notificationCallback: VoicemailNotificationCallback,
  callbackSchedule: string,
): VoicemailBoxInput {
  return {
    name: form.name.trim(),
    mailbox: form.mailbox.trim(),
    assigned_extension_id: form.assigned_extension_id,
    timezone: form.timezone,
    notification_emails: form.notification_emails
      .split(/[\n,]/)
      .map((email) => email.trim())
      .filter(Boolean),
    transcribe: form.transcribe,
    require_pin: form.require_pin,
    pin: form.pin.trim() || null,
    ...configuration,
    notify_callback: callbackConfigured
      ? {
          ...notificationCallback,
          number: notificationCallback.number?.trim() || null,
          schedule: callbackSchedule
            .split(/[\s,]+/)
            .map((value) => value.trim())
            .filter(Boolean)
            .map(Number),
        }
      : null,
  }
}
