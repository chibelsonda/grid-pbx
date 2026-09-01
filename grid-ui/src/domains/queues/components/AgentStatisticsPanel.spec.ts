import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AgentStatisticsPanel from './AgentStatisticsPanel.vue'

describe('AgentStatisticsPanel', () => {
  it('renders projected aggregate performance and emits refresh', async () => {
    const view = mount(AgentStatisticsPanel, {
      props: {
        statistics: {
          observed_at: '2026-09-01T04:05:06+00:00',
          totals: {
            total_calls: 12,
            answered_calls: 9,
            missed_calls: 3,
            answer_rate_percentage: 75,
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
            },
          ],
          unresolved_agents: 1,
        },
        loading: false,
        refreshing: false,
        error: null,
      },
    })

    expect(view.text()).toContain('Live agent performance')
    expect(view.text()).toContain('Ada Lovelace')
    expect(view.text()).toContain('75%')
    expect(view.text()).toContain('1 statistics entry')
    expect(view.text()).not.toContain('agent_id')
    await view.get('button').trigger('click')
    expect(view.emitted('refresh')).toEqual([[]])
  })
})
