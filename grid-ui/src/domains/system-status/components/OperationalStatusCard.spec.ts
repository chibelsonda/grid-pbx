import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import OperationalStatusCard from './OperationalStatusCard.vue'

describe('OperationalStatusCard', () => {
  it('keeps the icon in the header and renders a separate full-width body', () => {
    const wrapper = mount(OperationalStatusCard, {
      props: { title: 'Presence', iconClass: 'text-brand-600' },
      slots: {
        icon: '<svg data-test="icon" />',
        status: '<span>Available</span>',
        default: '<p>Capability details</p>',
      },
    })

    expect(wrapper.get('header').text()).toContain('Presence')
    expect(wrapper.get('header').text()).toContain('Available')
    expect(wrapper.get('[data-test="icon"]').attributes('data-test')).toBe('icon')
    expect(wrapper.get('[data-operational-status-card-icon]').classes()).toEqual(
      expect.not.arrayContaining([
        expect.stringMatching(/^bg-/),
        expect.stringMatching(/^rounded(?:-|$)/),
      ]),
    )
    expect(wrapper.get('[data-operational-status-card-body]').text()).toBe('Capability details')
    expect(wrapper.get('[data-operational-status-card-body]').element.parentElement).toBe(
      wrapper.element,
    )
  })
})
