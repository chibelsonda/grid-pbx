import type { VoicemailBoxConfiguration } from './types/voicemail'

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
  }
}

export function hydrateVoicemailBoxConfiguration(
  source?: Partial<VoicemailBoxConfiguration>,
): VoicemailBoxConfiguration {
  return { ...defaultVoicemailBoxConfiguration(), ...source }
}
