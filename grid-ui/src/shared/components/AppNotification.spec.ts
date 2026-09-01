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
    expect(status.text()).toContain('Account changed')
    expect(status.text()).toContain('Now viewing Branch Office.')

    await wrapper.get('[aria-label="Dismiss notification"]').trigger('click')
    expect(wrapper.emitted('dismiss')).toEqual([[]])
  })
})
