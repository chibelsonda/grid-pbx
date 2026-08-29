import { describe, expect, it } from 'vitest'
import { defaultVoicemailBoxConfiguration } from '../voicemailForm'
import { voicemailBoxFormSchema, voicemailBoxFormSchemaFor } from './voicemailBoxFormSchema'

function validMailbox() {
  return {
    name: 'Reception voicemail',
    mailbox: '1001',
    assigned_extension_id: null,
    timezone: 'Asia/Manila',
    notification_emails: ['support@example.test'],
    transcribe: true,
    require_pin: true,
    pin: '123456',
    ...defaultVoicemailBoxConfiguration(),
  }
}

describe('voicemailBoxFormSchema', () => {
  it('accepts the supported mailbox configuration', () => {
    expect(voicemailBoxFormSchema.safeParse(validMailbox()).success).toBe(true)
  })

  it('rejects invalid identity, email, PIN, format, and playback settings', () => {
    const result = voicemailBoxFormSchema.safeParse({
      ...validMailbox(),
      mailbox: '10A1',
      notification_emails: ['invalid'],
      pin: '12',
      media_extension: 'ogg',
      seek_duration_ms: 300001,
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'mailbox',
        'notification_emails.0',
        'pin',
        'media_extension',
        'seek_duration_ms',
      ]),
    )
  })

  it('requires a protected PIN on create while preserving an existing PIN on edit', () => {
    const withoutPin = { ...validMailbox(), pin: null }

    expect(voicemailBoxFormSchema.safeParse(withoutPin).success).toBe(false)
    expect(voicemailBoxFormSchemaFor(true, true).safeParse(withoutPin).success).toBe(true)
    expect(voicemailBoxFormSchemaFor(true, false).safeParse(withoutPin).success).toBe(false)
  })

  it('validates callback configuration and notification disposition precedence', () => {
    const result = voicemailBoxFormSchema.safeParse({
      ...validMailbox(),
      save_after_notify: true,
      delete_after_notify: true,
      notify_callback: {
        disabled: false,
        number: null,
        attempts: 101,
        interval_s: 604801,
        timeout_s: 3601,
        schedule: [-1],
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'delete_after_notify',
        'notify_callback.number',
        'notify_callback.attempts',
        'notify_callback.interval_s',
        'notify_callback.timeout_s',
        'notify_callback.schedule.0',
      ]),
    )
  })
})
