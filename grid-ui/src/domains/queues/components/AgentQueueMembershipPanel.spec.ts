import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgentQueueMembershipPanel from './AgentQueueMembershipPanel.vue'

const assignedQueueId = '11111111-1111-4111-8111-111111111111'

function wrapper() {
  return mount(AgentQueueMembershipPanel, {
    props: {
      membership: {
        agent: {
          id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
          name: 'Ada Lovelace',
          extension: '1001',
        },
        assigned_queues: [{ id: assignedQueueId, name: 'Support' }],
        available_queues: [
          { id: '22222222-2222-4222-8222-222222222222', name: 'Sales' },
        ],
        unresolved_queues: 1,
        observed_at: '2026-09-01T04:05:06+00:00',
      },
      loading: false,
      saving: false,
      error: null,
      commandAccepted: false,
      canManage: true,
    },
  })
}

describe('AgentQueueMembershipPanel', () => {
  it('shows only public Queue references and emits a validated leave request', async () => {
    const view = wrapper()

    expect(view.text()).toContain('Support')
    expect(view.text()).toContain('1 Switch Queue assignment')
    expect(view.text()).not.toContain('switch_resource_id')
    await view.get('button:nth-of-type(2)').trigger('click')

    expect(view.emitted('change')).toEqual([
      [{ action: 'logout', queue_id: assignedQueueId }],
    ])
  })

  it('marks the Queue selector invalid instead of emitting an empty join request', async () => {
    const view = wrapper()
    const join = view.findAll('button').find((button) => button.text() === 'Join')

    await join!.trigger('click')

    expect(view.text()).toContain('Select a projected Queue.')
    expect(view.emitted('change')).toBeUndefined()
    expect(view.get('[aria-label="Queue to join"]').classes()).toContain('border-red-400')
  })
})
