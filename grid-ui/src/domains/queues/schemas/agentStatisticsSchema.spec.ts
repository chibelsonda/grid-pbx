import { describe, expect, it } from 'vitest'
import { agentStatisticsSchema } from './agentStatisticsSchema'

describe('agentStatisticsSchema', () => {
  it('keeps projected aggregates and strips private Switch fields', () => {
    const result = agentStatisticsSchema.parse({
      observed_at: '2026-09-01T04:05:06+00:00',
      totals: {
        total_calls: 10,
        answered_calls: 8,
        missed_calls: 2,
        answer_rate_percentage: 80,
      },
      agents: [
        {
          id: '11111111-1111-4111-8111-111111111111',
          name: 'Ada Lovelace',
          extension: '1001',
          total_calls: 10,
          answered_calls: 8,
          missed_calls: 2,
          answer_rate_percentage: 80,
          agent_id: 'private-agent-id',
          queue_id: 'private-queue-id',
        },
      ],
      unresolved_agents: 0,
      raw_statistics: { caller_id_number: '+15551234567' },
    })

    expect(result.agents[0]).not.toHaveProperty('agent_id')
    expect(result.agents[0]).not.toHaveProperty('queue_id')
    expect(result).not.toHaveProperty('raw_statistics')
  })
})
