import { describe, expect, it } from 'vitest'
import { queueOptionsSchema } from './queueOptionsSchema'

describe('queueOptionsSchema', () => {
  it('keeps the ACDc capability contract boolean and strips private response fields', () => {
    const result = queueOptionsSchema.parse({
      agents: [],
      media: [],
      capabilities: {
        configuration_available: true,
        live_agent_controls_available: false,
        agent_statistics_available: false,
        statistics_available: false,
        raw_status_response: { agent_id: 'private-agent-id' },
      },
      switch_account_id: 'private-account-id',
    })

    expect(result.capabilities).toEqual({
      configuration_available: true,
      live_agent_controls_available: false,
      agent_statistics_available: false,
      statistics_available: false,
    })
    expect(result).not.toHaveProperty('switch_account_id')
  })
})
