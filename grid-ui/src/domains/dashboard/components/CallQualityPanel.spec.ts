import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { callQualitySchema } from '../schemas/callQualitySchema'
import CallQualityPanel from './CallQualityPanel.vue'

const quality = callQualitySchema.parse({
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: null,
  range: '7d',
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  answer_time: {
    answered_inbound_calls: 3,
    average_pre_answer_seconds: 13,
    disclosure: 'Derived pre-answer time.',
  },
  potential_abandonment: {
    threshold_seconds: 15,
    inbound_calls: 6,
    unanswered_inbound_calls: 3,
    potential_calls: 2,
    rate: 33.3,
    disclosure: 'Heuristic only.',
  },
  duration_distribution: {
    total_calls: 7,
    bands: [
      ['under_30', 'Under 30 sec', 0, 29, 3, 42.9],
      ['30_to_59', '30–59 sec', 30, 59, 2, 28.6],
      ['1_to_5_minutes', '1–5 min', 60, 299, 1, 14.3],
      ['5_to_15_minutes', '5–15 min', 300, 899, 1, 14.3],
      ['15_minutes_plus', '15+ min', 900, null, 0, 0],
    ].map(([key, label, minimum_seconds, maximum_seconds, count, percentage]) => ({
      key,
      label,
      minimum_seconds,
      maximum_seconds,
      count,
      percentage,
    })),
  },
})

const global = {
  stubs: {
    RouterLink: {
      name: 'RouterLink',
      props: ['to'],
      template: '<a><slot /></a>',
    },
  },
}

describe('CallQualityPanel', () => {
  it('renders disclosed metrics and bounded Call History routes', () => {
    const wrapper = mount(CallQualityPanel, {
      props: { quality, loading: false, error: null, rangeLabel: '7 days' },
      global,
    })
    const links = wrapper.findAllComponents({ name: 'RouterLink' })

    expect(wrapper.text()).toContain('Average pre-answer time')
    expect(wrapper.text()).toContain('13s')
    expect(wrapper.text()).toContain('Potential abandonment')
    expect(wrapper.text()).toContain('Heuristic only.')
    expect(links).toHaveLength(7)
    expect(links[0]?.props('to').query).toMatchObject({
      direction: 'inbound',
      outcome: 'answered',
    })
    expect(links[1]?.props('to').query).toMatchObject({
      direction: 'inbound',
      outcome: 'unanswered',
      duration_max: '15',
    })
    expect(links[6]?.props('to').query).toMatchObject({ duration_min: '900' })
  })
})
