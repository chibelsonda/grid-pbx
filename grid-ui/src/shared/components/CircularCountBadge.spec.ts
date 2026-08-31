import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CircularCountBadge from './CircularCountBadge.vue'

describe('CircularCountBadge', () => {
  it('uses equal fixed dimensions for a circular count badge', () => {
    const wrapper = mount(CircularCountBadge, { props: { count: 7, label: 'Seven issues' } })

    expect(wrapper.classes()).toContain('size-8')
    expect(wrapper.classes()).toContain('rounded-full')
    expect(wrapper.attributes('aria-label')).toBe('Seven issues')
    expect(wrapper.text()).toBe('7')
  })

  it('caps long values without stretching the circle', () => {
    const wrapper = mount(CircularCountBadge, { props: { count: 125 } })

    expect(wrapper.text()).toBe('99+')
    expect(wrapper.attributes('title')).toBe('125')
  })
})
