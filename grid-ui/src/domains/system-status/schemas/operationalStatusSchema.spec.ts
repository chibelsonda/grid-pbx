import { describe, expect, it } from 'vitest'
import { operationalStatusSchema } from './operationalStatusSchema'

const payload = {
  observed_at: '2026-08-31T08:00:00+00:00',
  presence: {
    subscription_diagnostics_available: true,
    live_status_available: false,
    commands_available: false,
  },
  parking: {
    summary_available: true,
    active_call_count: 2,
    actions_available: false,
  },
}

describe('operationalStatusSchema', () => {
  it('accepts the safe capability and aggregate contract', () => {
    expect(operationalStatusSchema.parse(payload)).toEqual(payload)
  })

  it('rejects raw Switch presence and parked-call fields', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        parking: { ...payload.parking, slots: { '101': { 'Call-ID': 'private-call-id' } } },
      }),
    ).toThrow()
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        presence: { ...payload.presence, subscriptions: [{ contact: 'sip:private@10.0.0.8' }] },
      }),
    ).toThrow()
  })
})
