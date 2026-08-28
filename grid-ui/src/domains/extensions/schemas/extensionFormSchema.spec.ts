import { describe, expect, it } from 'vitest'
import { defaultExtensionUserConfiguration } from '../extensionForm'
import { extensionCreateSchema, extensionUpdateSchema } from './extensionFormSchema'

function validInput() {
  return {
    first_name: 'Alice',
    last_name: 'Operator',
    extension: '1001',
    username: 'alice.operator',
    email: 'alice@example.test',
    timezone: 'Asia/Manila',
    is_enabled: true,
    ...defaultExtensionUserConfiguration(),
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
})
