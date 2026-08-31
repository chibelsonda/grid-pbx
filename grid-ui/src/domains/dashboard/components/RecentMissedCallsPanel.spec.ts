import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { recentMissedCallsSchema } from '../schemas/recentMissedCallsSchema'
import RecentMissedCallsPanel from './RecentMissedCallsPanel.vue'

const base = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  range: '7d' as const,
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
}

const global = {
  stubs: {
    RouterLink: {
      name: 'RouterLink',
      props: ['to'],
      template: '<a><slot /></a>',
    },
  },
}

describe('RecentMissedCallsPanel', () => {
  it('renders an actionable bounded recent missed-call row', () => {
    const missedCalls = recentMissedCallsSchema.parse({
      ...base,
      total: 1,
      items: [
        {
          id: '5b678ad8-49c5-4cab-8622-aee696563723',
          started_at: '2026-08-31T10:00:00+00:00',
          caller: { name: 'Alice Caller', number: '+14155550100' },
          destination: { name: 'Support', number: '1001' },
          duration_seconds: 18,
          hangup_cause: 'NO_ANSWER',
        },
      ],
    })
    const wrapper = mount(RecentMissedCallsPanel, {
      props: { missedCalls, loading: false, error: null, rangeLabel: '7 days' },
      global,
    })

    expect(wrapper.text()).toContain('Recent missed calls')
    expect(wrapper.text()).toContain('Alice Caller')
    expect(wrapper.text()).toContain('+14155550100')
    expect(wrapper.text()).toContain('To Support')
    expect(wrapper.text()).toContain('View all 1')
    const links = wrapper.findAllComponents({ name: 'RouterLink' })
    expect(links).toHaveLength(2)
    expect(links[0]?.props('to')).toEqual({
      name: 'call-history',
      query: {
        direction: 'inbound',
        outcome: 'unanswered',
        started_after: base.from,
        started_before: base.to,
      },
    })
  })

  it('renders a clear empty state', () => {
    const missedCalls = recentMissedCallsSchema.parse({ ...base, total: 0, items: [] })
    const wrapper = mount(RecentMissedCallsPanel, {
      props: { missedCalls, loading: false, error: null, rangeLabel: '7 days' },
      global,
    })

    expect(wrapper.text()).toContain('No inbound missed calls were projected for this period.')
  })
})
