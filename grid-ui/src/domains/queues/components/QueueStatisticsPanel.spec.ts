import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import QueueStatisticsPanel from './QueueStatisticsPanel.vue'

describe('QueueStatisticsPanel', () => {
  it('renders aggregated activity without private call details and emits refresh', async () => {
    const view = mount(QueueStatisticsPanel, {
      props: {
        statistics: {
          observed_at: '2026-09-01T04:05:06+00:00',
          totals: {
            waiting: 2,
            handled: 1,
            abandoned: 3,
            processed: 4,
            average_wait_seconds: 65,
            average_talk_seconds: 120,
            longest_current_wait_seconds: 130,
          },
          queues: [
            {
              id: '11111111-1111-4111-8111-111111111111',
              name: 'Support',
              waiting: 2,
              handled: 1,
              abandoned: 3,
              processed: 4,
              average_wait_seconds: 65,
              average_talk_seconds: 120,
              longest_current_wait_seconds: 130,
            },
          ],
          unresolved_records: 1,
        },
        loading: false,
        refreshing: false,
        error: null,
      },
    })

    expect(view.text()).toContain('Live queue activity')
    expect(view.text()).toContain('1m 5s')
    expect(view.text()).toContain('Support')
    expect(view.text()).toContain('1 unresolved statistic record')
    expect(view.text()).not.toContain('caller_id')
    await view.get('button').trigger('click')
    expect(view.emitted('refresh')).toEqual([[]])
  })
})
