import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/authStore'
import ForgotPasswordPage from './ForgotPasswordPage.vue'

describe('ForgotPasswordPage', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('validates the email before submitting', async () => {
    const auth = useAuthStore()
    const requestPasswordReset = vi.spyOn(auth, 'requestPasswordReset')
    const wrapper = mount(ForgotPasswordPage, {
      global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    })

    await wrapper.get('input[name="email"]').setValue('not-an-email')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.text()).toContain('Enter a valid email address.')
    expect(requestPasswordReset).not.toHaveBeenCalled()
  })

  it('shows the generic success response and allows another request', async () => {
    const auth = useAuthStore()
    const requestPasswordReset = vi
      .spyOn(auth, 'requestPasswordReset')
      .mockImplementation(async () => {
        auth.passwordResetMessage =
          'If an account exists for that email address, a password reset link has been sent.'
        return true
      })
    const wrapper = mount(ForgotPasswordPage, {
      global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    })

    await wrapper.get('input[name="email"]').setValue(' Owner@Example.test ')
    await wrapper.get('form').trigger('submit')

    expect(requestPasswordReset).toHaveBeenCalledWith({ email: 'owner@example.test' })
    expect(wrapper.get('[role="status"]').text()).toContain('If an account exists')
    expect(wrapper.get('button[type="submit"]').text()).toBe('Send reset link')
    expect(wrapper.text()).toContain('Back to sign in')
  })

  it('disables the submit action while the request is pending', async () => {
    const auth = useAuthStore()
    let finishRequest: ((value: boolean) => void) | undefined
    vi.spyOn(auth, 'requestPasswordReset').mockImplementation(
      () => new Promise<boolean>((resolve) => (finishRequest = resolve)),
    )
    const wrapper = mount(ForgotPasswordPage, {
      global: { stubs: { RouterLink: true } },
    })

    await wrapper.get('input[name="email"]').setValue('owner@example.test')
    await wrapper.get('form').trigger('submit')
    auth.passwordResetLoading = true
    await wrapper.vm.$nextTick()

    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button[type="submit"]').text()).toBe('Sending…')

    finishRequest?.(true)
    await flushPromises()
  })
})
