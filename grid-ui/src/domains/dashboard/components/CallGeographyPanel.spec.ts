import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { callGeographySchema } from '../schemas/callGeographySchema'
import CallGeographyPanel from './CallGeographyPanel.vue'

const base = {
  generated_at: '2026-08-31T12:00:00+00:00',
  data_as_of: '2026-08-31T11:55:00+00:00',
  range: '7d' as const,
  timezone: 'UTC',
  from: '2026-08-25T00:00:00+00:00',
  to: '2026-09-01T00:00:00+00:00',
  disclosure: 'Estimated numbering-plan geography, not a live location.',
}

describe('CallGeographyPanel', () => {
  it('explains the disabled capability without contacting a tile provider', () => {
    const geography = callGeographySchema.parse({
      ...base,
      status: 'unavailable',
      capability: { available: false, source: null, reason: 'Approved source required.' },
      coverage: { total_calls: 0, located_calls: 0, percentage: 0 },
      locations: [],
    })
    const wrapper = mount(CallGeographyPanel, {
      props: { geography, loading: false, error: null, rangeLabel: '7 days' },
    })

    expect(wrapper.text()).toContain('Geography analytics not enabled')
    expect(wrapper.text()).toContain('will not geocode phone numbers')
    expect(wrapper.find('svg[aria-label="Estimated call geography map"]').exists()).toBe(false)
  })

  it('renders the local map and matching accessible location summary', async () => {
    const geography = callGeographySchema.parse({
      ...base,
      status: 'ready',
      capability: { available: true, source: 'approved-source', reason: null },
      coverage: { total_calls: 5, located_calls: 5, percentage: 100 },
      locations: [
        {
          key: 'seattle',
          label: 'Seattle, WA, US',
          locality: 'Seattle',
          region_code: 'WA',
          country_code: 'US',
          latitude: 47.6062,
          longitude: -122.3321,
          precision: 'numbering_plan',
          total: 3,
          inbound: 2,
          outbound: 1,
        },
        {
          key: 'san-francisco',
          label: 'San Francisco, CA, US',
          locality: 'San Francisco',
          region_code: 'CA',
          country_code: 'US',
          latitude: 37.7749,
          longitude: -122.4194,
          precision: 'numbering_plan',
          total: 2,
          inbound: 1,
          outbound: 1,
        },
      ],
    })
    const wrapper = mount(CallGeographyPanel, {
      props: { geography, loading: false, error: null, rangeLabel: '7 days' },
    })

    expect(wrapper.find('svg[aria-label="Estimated call geography map"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Accessible non-map view of the same data')
    await wrapper.get('[aria-label="San Francisco, CA, US: 2 estimated calls"]').trigger('click')
    expect(wrapper.text()).toContain('1 inbound · 1 outbound')
  })
})
