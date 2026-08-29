import { describe, expect, it } from 'vitest'
import {
  defaultExtensionAdvancedCallingConfiguration,
  defaultExtensionHotdeskInput,
  defaultExtensionUserConfiguration,
  hydrateExtensionAdvancedCalling,
} from '../extensionForm'
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

function validUpdateInput() {
  return {
    ...validInput(),
    ...hydrateExtensionAdvancedCalling(defaultExtensionAdvancedCallingConfiguration(), []),
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
          input: null,
        },
      }).success,
    ).toBe(true)
    expect(
      extensionUpdateSchema.safeParse({
        ...validUpdateInput(),
        voicemail: { ...input.voicemail, pin: null },
      }).success,
    ).toBe(true)
  })

  it('rejects unsafe User metaflow actions', () => {
    const result = extensionUpdateSchema.safeParse({
      ...validUpdateInput(),
      metaflows: {
        binding_digit: '*',
        digit_timeout: 2000,
        listen_on: 'both',
        actions: [
          {
            trigger_type: 'pattern',
            trigger: '(?R)',
            module: 'play',
            data: { media_id: 'private-switch-id' },
            children: [],
          },
        ],
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['metaflows.actions.0.trigger', 'metaflows.actions.0.data.media_id']),
    )
  })

  it('rejects invalid identity, mailbox, and missing initial-device configuration', () => {
    const result = extensionCreateSchema.safeParse({
      ...validInput(),
      extension: '1A',
      voicemail: { ...validInput().voicemail, pin: null },
      device: {
        enabled: true,
        input: null,
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['extension', 'voicemail.pin', 'device.input']),
    )
  })

  it('rejects unsupported caller ID privacy modes', () => {
    const result = extensionUpdateSchema.safeParse({
      ...validUpdateInput(),
      caller_id_options: { outbound_privacy: 'secret' },
    })

    expect(result.success).toBe(false)
  })

  it('attaches an enabled forwarding error to the destination alongside other errors', () => {
    const result = extensionUpdateSchema.safeParse({
      ...validUpdateInput(),
      first_name: '',
      call_forward: {
        ...validUpdateInput().call_forward,
        enabled: true,
        number: null,
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['first_name', 'call_forward.number']),
    )
  })

  it('rejects duplicate codecs and invalid User media values', () => {
    const result = extensionUpdateSchema.safeParse({
      ...validUpdateInput(),
      media: {
        ...validUpdateInput().media,
        audio: { codecs: ['PCMU', 'PCMU'] },
        progress_timeout: 3601,
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['media.audio.codecs', 'media.progress_timeout']),
    )
  })

  it('rejects unsafe advanced User routing and incomplete profile addresses', () => {
    const result = extensionUpdateSchema.safeParse({
      ...validUpdateInput(),
      dial_plan: {
        system: [],
        rules: [
          { pattern: '(?R)', description: null, prefix: null, suffix: null },
          { pattern: '(?R)', description: null, prefix: null, suffix: null },
        ],
      },
      formatters: [
        {
          field: 'request-header',
          direction: 'both',
          match_invite_format: false,
          prefix: null,
          regex: '(?R)',
          strip: false,
          suffix: null,
          value: null,
        },
      ],
      profile: {
        ...validUpdateInput().profile,
        addresses: [{ address: '', types: ['work'] }],
      },
    })

    expect(result.success).toBe(false)
    if (result.success) return

    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'dial_plan.rules.0.pattern',
        'dial_plan.rules.1.pattern',
        'formatters.0.field',
        'formatters.0.regex',
        'profile.addresses.0.address',
      ]),
    )
  })

  it('accepts a configured full-editor device and rejects hidden disabled configuration', () => {
    const configuredDevice = extensionCreateSchema.safeParse({
      ...validInput(),
      device: {
        enabled: true,
        input: {
          name: 'Mobile forwarding',
          device_type: 'cellphone',
          is_enabled: true,
          assigned_extension_id: null,
        },
      },
    })
    const disabledCredentials = extensionCreateSchema.safeParse({
      ...validInput(),
      device: {
        enabled: false,
        input: {
          name: 'Hidden device',
          device_type: 'sip_device',
          is_enabled: true,
          assigned_extension_id: null,
        },
      },
    })

    expect(configuredDevice.success).toBe(true)
    expect(disabledCredentials.success).toBe(false)
  })

  it('requires and confirms a password when creating or changing a login username', () => {
    const missingCreatePassword = extensionCreateSchema.safeParse({
      ...validInput(),
      password: null,
      password_confirmation: null,
      device: {
        enabled: false,
        input: null,
      },
    })
    const unchangedLogin = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validUpdateInput(),
      password: null,
      password_confirmation: null,
    })
    const changedLogin = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validUpdateInput(),
      username: 'alice.changed',
      password: null,
      password_confirmation: null,
    })
    const mismatchedPassword = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validUpdateInput(),
      password_confirmation: 'different-password',
    })

    expect(missingCreatePassword.success).toBe(false)
    expect(unchangedLogin.success).toBe(true)
    expect(changedLogin.success).toBe(false)
    expect(mismatchedPassword.success).toBe(false)
  })

  it('requires explicit confirmation before removing configured login credentials', () => {
    const unconfirmed = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validUpdateInput(),
      username: null,
      password: null,
      password_confirmation: null,
      require_password_update: false,
      clear_credentials: false,
    })
    const confirmed = extensionUpdateSchemaFor('alice.operator').safeParse({
      ...validUpdateInput(),
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
        input: null,
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
      ...validUpdateInput(),
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
      ...validUpdateInput(),
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
