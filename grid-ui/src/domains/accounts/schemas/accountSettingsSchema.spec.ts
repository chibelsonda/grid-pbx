import { describe, expect, it } from 'vitest'
import { accountSettingsSchema } from './accountSettingsSchema'
import type {
  AccountCallRecording,
  AccountRecordingParameters,
  AccountRecordingSource,
} from '../types/account'

const parameters = (): AccountRecordingParameters => ({
  enabled: false,
  format: 'mp3',
  record_min_sec: null,
  record_on_answer: true,
  record_on_bridge: false,
  record_sample_rate: null,
  time_limit: null,
})
const source = (): AccountRecordingSource => ({
  any: parameters(),
  onnet: parameters(),
  offnet: parameters(),
})
const recording = (): AccountCallRecording => ({
  account: { any: source(), inbound: source(), outbound: source() },
  endpoint: { any: source(), inbound: source(), outbound: source() },
})
const input = () => ({
  name: 'Grid Support',
  organization_name: '',
  timezone: 'Asia/Manila',
  language: 'en-US',
  call_waiting_enabled: true,
  do_not_disturb_enabled: false,
  outbound_privacy: 'none',
  show_rate: false,
  ringtone_internal: '',
  ringtone_external: '',
  caller_id: {
    internal: { name: 'Support', number: '1000' },
    external: { name: '', phone_number_id: null, preserve_number: false },
    emergency: { name: '', phone_number_id: null, preserve_number: false },
  },
  call_restriction: { international: { action: 'deny' } },
  call_recording: recording(),
  dial_plan: { system: ['north_america'], rules: [] },
  formatters: [],
  preflow: { callflow_id: null, preserve_callflow: false },
  metaflows: { binding_digit: '*', digit_timeout: 2000, listen_on: 'both' as const, actions: [] },
})

describe('account settings schema', () => {
  it('accepts the bounded restriction and recording matrix', () => {
    const candidate = input()
    candidate.call_recording.account.inbound.offnet = {
      enabled: true,
      format: 'wav',
      record_min_sec: 5,
      record_on_answer: true,
      record_on_bridge: false,
      record_sample_rate: 16000,
      time_limit: 3600,
    }

    expect(accountSettingsSchema.safeParse(candidate).success).toBe(true)
  })

  it('matches the installed Account timezone and ringtone bounds', () => {
    const candidate = input()
    ;(candidate as unknown as Record<string, unknown>).outbound_privacy = null
    candidate.ringtone_internal = 'r'.repeat(256)

    expect(accountSettingsSchema.safeParse(candidate).success).toBe(true)

    candidate.timezone = 'UTC'
    candidate.ringtone_external = 'r'.repeat(257)

    const result = accountSettingsSchema.safeParse(candidate)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['timezone', 'ringtone_external']),
    )
  })

  it('rejects unsafe classification keys and recording limits', () => {
    const candidate = input()
    const restrictions = candidate.call_restriction as Record<
      string,
      { action: 'inherit' | 'deny' }
    >
    restrictions['../../international'] = { action: 'deny' }
    candidate.call_recording.endpoint.outbound.offnet.time_limit = 20_000

    const result = accountSettingsSchema.safeParse(candidate)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.some((issue) => issue.path[0] === 'call_restriction')).toBe(true)
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toContain(
      'call_recording.endpoint.outbound.offnet.time_limit',
    )
  })

  it('rejects duplicate or unsupported dial-plan and formatter expressions', () => {
    const candidate = input()
    const dialPlanRules = [
      { pattern: '^([0-9]+)$', description: '', prefix: '', suffix: '' },
      { pattern: '^([0-9]+)$', description: '', prefix: '', suffix: '' },
    ]
    const formatters = [
      {
        field: 'request',
        direction: 'both' as const,
        match_invite_format: false,
        prefix: '',
        regex: '(?R)',
        strip: false,
        suffix: '',
        value: '',
      },
    ]
    const raw = candidate as unknown as Record<string, unknown>
    raw.dial_plan = { system: candidate.dial_plan.system, rules: dialPlanRules }
    raw.formatters = formatters

    const result = accountSettingsSchema.safeParse(candidate)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['dial_plan.rules.1.pattern', 'formatters.0.regex']),
    )
  })

  it('validates preflow UUIDs and bounded metaflow activation settings', () => {
    const candidate = input()
    const raw = candidate as unknown as Record<string, unknown>
    raw.preflow = { callflow_id: 'internal-switch-id', preserve_callflow: false }
    raw.metaflows = { binding_digit: 'A', digit_timeout: 60_001, listen_on: 'other', actions: [] }

    const result = accountSettingsSchema.safeParse(candidate)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining([
        'preflow.callflow_id',
        'metaflows.binding_digit',
        'metaflows.digit_timeout',
        'metaflows.listen_on',
      ]),
    )
  })

  it('rejects unsafe or incomplete recursive metaflow actions', () => {
    const candidate = input()
    ;(candidate.metaflows.actions as unknown[]).push({
      trigger_type: 'pattern',
      trigger: '(?R)',
      module: 'play',
      data: { media_id: 'internal-switch-id' },
      children: [],
    })

    const result = accountSettingsSchema.safeParse(candidate)

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
      expect.arrayContaining(['metaflows.actions.0.trigger', 'metaflows.actions.0.data.media_id']),
    )
  })
})
