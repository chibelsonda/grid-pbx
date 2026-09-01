import { describe, expect, it } from 'vitest'
import { agentAvailabilitySchema } from './agentAvailabilitySchema'

describe('agentAvailabilitySchema', () => {
  it('accepts public status rows and strips unexpected private fields', () => {
    const result = agentAvailabilitySchema.parse({
      observed_at: '2026-09-01T04:05:06+00:00',
      agents: [
        {
          id: '11111111-1111-4111-8111-111111111111',
          status: 'connected',
          changed_at: 63800000000,
          call_id: 'private-call-id',
        },
      ],
      unresolved_agents: 0,
    })

    expect(result.agents[0]).toEqual({
      id: '11111111-1111-4111-8111-111111111111',
      status: 'connected',
      changed_at: 63800000000,
    })
  })

  it('rejects an unknown status', () => {
    expect(() =>
      agentAvailabilitySchema.parse({
        observed_at: '2026-09-01T04:05:06+00:00',
        agents: [
          {
            id: '11111111-1111-4111-8111-111111111111',
            status: 'future-status',
            changed_at: 63800000000,
          },
        ],
        unresolved_agents: 0,
      }),
    ).toThrow()
  })
})
