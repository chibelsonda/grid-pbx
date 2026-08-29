import { describe, expect, it } from 'vitest'
import { defaultExtensionHotdeskInput, defaultExtensionUserConfiguration } from '../extensionForm'
import {
  extensionCreateSchema,
  extensionUpdateSchema,
  extensionUpdateSchemaFor,
} from './extensionFormSchema'

function validInput() {
  return {
    first_name: 'Alice',
    last_name: 'Operator',
    extension: '1001',
    username: 'alice.operator',
    password: 'correct-horse-battery-staple',
    password_confirmation: 'correct-horse-battery-staple',
    require_password_update: true,
    clear_credentials: false,
    email: 'alice@example.test',
    timezone: 'Asia/Manila',
    is_enabled: true,
    ...defaultExtensionUserConfiguration(),
    hotdesk: defaultExtensionHotdeskInput(),
    voicemail: {
      enabled: true,
      notification_emails: ['alice@example.test'],
      transcribe: false,
      require_pin: true,
      pin: '1234',
    },
  }
}

describe('extension form schemas', () => {
  it('accepts valid create and edit payloads', () => {
    const input = validInput()

    expect(
      extensionCreateSchema.safeParse({
        ...input,
        device: {
          enabled: false,
          name: null,
          device_type: null,
          make: null,
          model: null,
          mac_address: null,
          sip_username: null,
          sip_password: null,
        },
      }).success,
    ).toBe(true)
    expect(
      extensionUpdateSchema.safeParse({ ...input, voicemail: { ...input.voicemail, pin: null } })
        .success,
    ).toBe(true)
  })

  it('rejects invalid identity, mailbox, and initial-device fields', () => {
    const result = extensionCreateSchema.safeParse({
      ...validInput(),
      extension: '1A',
      voicemail: { ...validInput().voicemail, pin: null },
      device: {
        enabled: true,
        name: null,
        device_type: 'sip_device',
        make: null,
        model: null,
        mac_address: 'invalid',
        sip_username: null,
        sip_password: 'short',
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'extension',
        'voicemail.pin',
        'device.name',
        'device.mac_address',
        'device.sip_password',
      ]),
    )
  })

  it('rejects unsupported caller ID privacy modes', () => {
    const result = extensionUpdateSchema.safeParse({
      ...validInput(),
      caller_id_options: { outbound_privacy: 'secret' },
    })

    expect(result.success).toBe(false)
  })

  it('requires and confirms a password when creating or changing a login username', () => {
    const missingCreatePassword = extensionCreateSchema.safeParse({
      ...validInput(),
      password: null,
      password_confirmation: null,
      device: {
        enabled: false,
        name: null,
        device_type: null,
        make: null,
        model: null,
        mac_address: null,
        sip_username: null,
        sip_password: null,
      },
    })
    const unchangedLogin = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validInput(),
      password: null,
      password_confirmation: null,
    })
    const changedLogin = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validInput(),
      username: 'alice.changed',
      password: null,
      password_confirmation: null,
    })
    const mismatchedPassword = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validInput(),
      password_confirmation: 'different-password',
    })

    expect(missingCreatePassword.success).toBe(false)
    expect(unchangedLogin.success).toBe(true)
    expect(changedLogin.success).toBe(false)
    expect(mismatchedPassword.success).toBe(false)
  })

  it('requires explicit confirmation before removing configured login credentials', () => {
    const unconfirmed = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validInput(),
      username: null,
      password: null,
      password_confirmation: null,
      require_password_update: false,
      clear_credentials: false,
    })
    const confirmed = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validInput(),
      username: null,
      password: null,
      password_confirmation: null,
      require_password_update: false,
      clear_credentials: true,
    })

    expect(unconfirmed.success).toBe(false)
    expect(confirmed.success).toBe(true)
  })

  it('requires schema-compatible hotdesk identity and PIN values on create', () => {
    const result = extensionCreateSchema.safeParse({
      ...validInput(),
      hotdesk: {
        enabled: true,
        id: 'abc',
        keep_logged_in_elsewhere: false,
        require_pin: true,
        pin: null,
        clear_pin: false,
      },
      device: {
        enabled: false,
        name: null,
        device_type: null,
        make: null,
        model: null,
        mac_address: null,
        sip_username: null,
        sip_password: null,
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['hotdesk.id', 'hotdesk.pin']),
    )
  })

  it('allows an unchanged write-only hotdesk PIN on edit but rejects clearing a required PIN', () => {
    const unchanged = extensionUpdateSchema.safeParse({
      ...validInput(),
      hotdesk: {
        enabled: true,
        id: '1001',
        keep_logged_in_elsewhere: true,
        require_pin: true,
        pin: null,
        clear_pin: false,
      },
    })
    const invalidClear = extensionUpdateSchema.safeParse({
      ...validInput(),
      hotdesk: {
        enabled: true,
        id: '1001',
        keep_logged_in_elsewhere: true,
        require_pin: true,
        pin: null,
        clear_pin: true,
      },
    })

    expect(unchanged.success).toBe(true)
    expect(invalidClear.success).toBe(false)
  })
})
