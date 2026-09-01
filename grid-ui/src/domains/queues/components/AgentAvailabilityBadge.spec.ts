import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgentAvailabilityBadge from './AgentAvailabilityBadge.vue'

describe('AgentAvailabilityBadge', () => {
  it.each([
    ['ready', 'Available'],
    ['connected', 'On call'],
    ['wrapup', 'Wrap-up'],
    ['paused', 'Paused'],
    ['logged_out', 'Logged out'],
    [null, 'Unknown'],
  ] as const)('presents %s as %s', (status, label) => {
    const view = mount(AgentAvailabilityBadge, { props: { status } })

    expect(view.text()).toBe(label)
  })
})
