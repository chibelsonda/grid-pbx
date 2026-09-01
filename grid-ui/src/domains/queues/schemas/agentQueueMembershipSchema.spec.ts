import { describe, expect, it } from 'vitest'
import { agentQueueMembershipSchema } from './agentQueueMembershipSchema'

describe('agentQueueMembershipSchema', () => {
  it('keeps public references and strips unexpected private Switch fields', () => {
    const result = agentQueueMembershipSchema.parse({
      agent: {
        id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        name: 'Ada Lovelace',
        extension: '1001',
        switch_resource_id: 'private-agent-id',
      },
      assigned_queues: [
        {
          id: '11111111-1111-4111-8111-111111111111',
          name: 'Support',
          switch_resource_id: 'private-queue-id',
        },
      ],
      available_queues: [],
      unresolved_queues: 0,
      agent_active: true,
      observed_at: '2026-09-01T04:05:06+00:00',
    })

    expect(result.agent).not.toHaveProperty('switch_resource_id')
    expect(result.assigned_queues[0]).toEqual({
      id: '11111111-1111-4111-8111-111111111111',
      name: 'Support',
    })
  })
})
