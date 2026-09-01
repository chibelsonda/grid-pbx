import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgentStatusPanel from './AgentStatusPanel.vue'
import type { Agent } from '../types/queue'

const agent: Agent = {
  id: 'public-agent',
  name: 'Ada Lovelace',
  extension: '1001',
  queues: [{ id: 'public-queue', name: 'Support' }],
}

function wrapper(overrides: Record<string, unknown> = {}) {
  return mount(AgentStatusPanel, {
    props: {
      agent,
      current: { id: agent.id, status: 'connected', timestamp: 63800000000 },
      loading: false,
      refreshing: false,
      lastObservedAt: '2026-09-01T04:05:06.000Z',
      refreshError: null,
      commandAccepted: false,
      membership: {
        agent: { id: agent.id, name: agent.name, extension: agent.extension },
        assigned_queues: agent.queues,
        available_queues: [],
        unresolved_queues: 0,
        observed_at: '2026-09-01T04:05:06.000Z',
      },
      membershipLoading: false,
      membershipSaving: false,
      membershipError: null,
      membershipCommandAccepted: false,
      error: null,
      fieldErrors: {},
      canManage: true,
      ...overrides,
    },
    global: {
      stubs: {
        CrudSlideOver: { template: '<section><slot /></section>' },
      },
    },
  })
}

describe('AgentStatusPanel', () => {
  it('identifies the live polling interval and emits a manual refresh', async () => {
    const view = wrapper()

    expect(view.text()).toContain('Auto-refresh · 5s')
    expect(view.text()).toContain('connected')
    await view.get('button').trigger('click')

    expect(view.emitted('refresh')).toEqual([[]])
  })

  it('distinguishes command acceptance from an observed status transition', () => {
    const view = wrapper({ commandAccepted: true })

    expect(view.text()).toContain('Switch accepted the status command')
    expect(view.text()).toContain('commands can be deferred while the agent is on a call')
    expect(view.text()).toContain('connected')
  })

  it('retains the last status and presents a safe background refresh error', () => {
    const view = wrapper({ refreshError: 'Unable to refresh live agent status.' })

    expect(view.get('[role="alert"]').text()).toContain('Unable to refresh live agent status.')
    expect(view.text()).toContain('The last observed status remains displayed.')
    expect(view.text()).toContain('connected')
  })
})
