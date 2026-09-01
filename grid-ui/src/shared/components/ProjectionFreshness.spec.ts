import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ProjectionFreshness from './ProjectionFreshness.vue'

describe('ProjectionFreshness', () => {
  it('renders compact synchronization context as a live status', () => {
    const wrapper = mount(ProjectionFreshness, {
      props: { lastSynchronizedAt: '2026-09-01T09:24:00' },
    })

    const status = wrapper.get('[data-testid="projection-freshness"]')
    expect(status.attributes('role')).toBe('status')
    expect(status.attributes('aria-live')).toBe('polite')
    expect(status.text()).toBe('Last synchronized Sep 1, 2026, 9:24 AM')
  })

  it('keeps the last successful timestamp when the latest synchronization failed', () => {
    const wrapper = mount(ProjectionFreshness, {
      props: { lastSynchronizedAt: '2026-09-01T09:24:00', status: 'error' },
    })

    expect(wrapper.text()).toBe(
      'Last synchronization failed · Last successful Sep 1, 2026, 9:24 AM',
    )
    expect(wrapper.get('[role="status"]').classes()).toContain('text-danger')
  })
})
