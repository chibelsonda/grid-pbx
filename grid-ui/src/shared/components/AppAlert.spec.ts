import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AppAlert from './AppAlert.vue'

describe('AppAlert', () => {
  afterEach(() => vi.useRealTimers())

  it('renders a compact, tone-aware alert and supports manual dismissal', async () => {
    const wrapper = mount(AppAlert, {
      props: {
        compact: true,
        message: 'The device was updated.',
        title: 'Update successful',
        tone: 'success',
      },
    })

    const alert = wrapper.get('[role="status"]')
    expect(alert.classes()).toContain('max-w-sm')
    expect(alert.classes()).toContain('app-notification-success')

    await wrapper.get('[aria-label="Dismiss alert"]').trigger('click')
    expect(wrapper.emitted('dismiss')).toEqual([[]])
  })

  it('automatically dismisses transient alerts after the configured duration', async () => {
    vi.useFakeTimers()
    const wrapper = mount(AppAlert, {
      props: {
        autoClose: true,
        duration: 2_000,
        message: 'Saved.',
        tone: 'success',
      },
    })

    await vi.advanceTimersByTimeAsync(1_999)
    expect(wrapper.emitted('dismiss')).toBeUndefined()

    await vi.advanceTimersByTimeAsync(1)
    expect(wrapper.emitted('dismiss')).toEqual([[]])
  })

  it('keeps persistent page alerts visible until dismissed', async () => {
    vi.useFakeTimers()
    const wrapper = mount(AppAlert, {
      props: {
        message: 'Unable to load devices.',
        tone: 'error',
      },
    })

    await vi.runAllTimersAsync()
    expect(wrapper.emitted('dismiss')).toBeUndefined()
    expect(wrapper.get('[role="alert"]').attributes('aria-live')).toBe('assertive')
  })
})
