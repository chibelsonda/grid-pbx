import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppNotification from './AppNotification.vue'

describe('AppNotification', () => {
  it('announces a public message and can be dismissed', async () => {
    const wrapper = mount(AppNotification, {
      props: {
        show: true,
        title: 'Account changed',
        message: 'Now viewing Branch Office.',
      },
    })

    const status = wrapper.get('[role="status"]')
    expect(status.attributes('aria-live')).toBe('polite')
    expect(status.classes()).toContain('app-notification')
    expect(status.classes()).toContain('app-notification-info')
    expect(status.attributes('data-tone')).toBe('info')
    expect(status.get('.app-notification-accent').classes()).toContain('app-notification-accent')
    expect(status.text()).toContain('Account changed')
    expect(status.text()).toContain('Now viewing Branch Office.')

    await wrapper.get('[aria-label="Dismiss notification"]').trigger('click')
    expect(wrapper.emitted('dismiss')).toEqual([[]])
  })

  it.each([
    ['success', 'status', 'polite'],
    ['warning', 'alert', 'assertive'],
    ['error', 'alert', 'assertive'],
  ] as const)('applies the matching %s alert treatment', (tone, role, liveMode) => {
    const wrapper = mount(AppNotification, {
      props: {
        show: true,
        title: 'Request status',
        message: 'The request has an updated status.',
        tone,
      },
    })

    const notification = wrapper.get(`[role="${role}"]`)
    expect(notification.attributes('aria-live')).toBe(liveMode)
    expect(notification.attributes('data-tone')).toBe(tone)
    expect(notification.classes()).toContain(`app-notification-${tone}`)
    expect(notification.get('.app-notification-accent').exists()).toBe(true)
  })
})
