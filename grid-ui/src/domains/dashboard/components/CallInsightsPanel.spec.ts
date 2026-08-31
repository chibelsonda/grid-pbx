import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { callActivityTrendSchema } from '../schemas/callActivityTrendSchema'
import { topCallDestinationsSchema } from '../schemas/topCallDestinationsSchema'
import CallInsightsPanel from './CallInsightsPanel.vue'

const global = {
  stubs: {
    RouterLink: {
      name: 'RouterLink',
      props: ['to'],
      template: '<a><slot /></a>',
    },
  },
}

describe('CallInsightsPanel', () => {
  it('renders the busiest period and safe destination drill-downs', () => {
    const activity = callActivityTrendSchema.parse({
      range: '7d',
      granularity: 'day',
      timezone: 'UTC',
      from: '2026-08-25T00:00:00+00:00',
      to: '2026-09-01T00:00:00+00:00',
      totals: {
        total: 7,
        inbound: 5,
        outbound: 2,
        answered: 6,
        missed: 1,
        answer_rate: 85.7,
        average_duration_seconds: 45,
      },
      series: [
        {
          start_at: '2026-08-25T00:00:00+00:00',
          end_at: '2026-08-26T00:00:00+00:00',
          total: 2,
          inbound: 1,
          outbound: 1,
          answered: 2,
          missed: 0,
        },
        {
          start_at: '2026-08-26T00:00:00+00:00',
          end_at: '2026-08-27T00:00:00+00:00',
          total: 5,
          inbound: 4,
          outbound: 1,
          answered: 4,
          missed: 1,
        },
      ],
    })
    const destinations = topCallDestinationsSchema.parse({
      generated_at: '2026-08-31T12:00:00+00:00',
      data_as_of: null,
      range: '7d',
      timezone: 'UTC',
      from: activity.from,
      to: activity.to,
      destinations: [
        {
          name: 'Support',
          number: '1001',
          total: 5,
          inbound: 3,
          outbound: 2,
          answered: 4,
          unanswered: 1,
        },
      ],
    })
    const wrapper = mount(CallInsightsPanel, {
      props: { activity, destinations, loading: false, error: null, rangeLabel: '7 days' },
      global,
    })

    expect(wrapper.text()).toContain('Peak calling day')
    expect(wrapper.text()).toContain('4 inbound · 1 outbound')
    expect(wrapper.text()).toContain('Support')
    expect(wrapper.text()).toContain('4 answered · 1 unanswered')
    expect(wrapper.getComponent({ name: 'RouterLink' }).props('to')).toEqual({
      name: 'call-history',
      query: {
        started_after: activity.from,
        started_before: activity.to,
        search: '1001',
      },
    })
  })

  it('renders a clear no-activity state', () => {
    const wrapper = mount(CallInsightsPanel, {
      props: {
        activity: null,
        destinations: null,
        loading: false,
        error: null,
        rangeLabel: 'Today',
      },
      global,
    })

    expect(wrapper.text()).toContain('No calls were projected in this period.')
  })
})
