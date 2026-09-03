import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/authStore'
import ResetPasswordPage from './ResetPasswordPage.vue'

const push = vi.fn<(location: unknown) => Promise<void>>().mockResolvedValue()
let query: Record<string, string> = {}

vi.mock('vue-router', () => ({
  useRoute: () => ({ query }),
  useRouter: () => ({ push }),
}))

describe('ResetPasswordPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    query = {}
    push.mockReset()
  })

  it('blocks an incomplete reset link', () => {
    const wrapper = mount(ResetPasswordPage, {
      global: { stubs: { RouterLink: true } },
    })

    expect(wrapper.get('[role="alert"]').text()).toContain('link is incomplete')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('validates matching strong passwords before submitting', async () => {
    query = { email: 'owner@example.test', token: 'reset-token' }
    const auth = useAuthStore()
    const resetPassword = vi.spyOn(auth, 'resetPassword')
    const wrapper = mount(ResetPasswordPage, {
      global: { stubs: { RouterLink: true } },
    })

    await wrapper.get('input[name="password"]').setValue('New-password2!')
    await wrapper.get('input[name="password_confirmation"]').setValue('Different-password3!')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.text()).toContain('Passwords must match.')
    expect(resetPassword).not.toHaveBeenCalled()
  })

  it('submits query credentials and returns to login after success', async () => {
    query = { email: 'owner@example.test', token: 'reset-token' }
    const auth = useAuthStore()
    const resetPassword = vi.spyOn(auth, 'resetPassword').mockResolvedValue(true)
    const wrapper = mount(ResetPasswordPage, {
      global: { stubs: { RouterLink: true } },
    })

    await wrapper.get('input[name="password"]').setValue('New-password2!')
    await wrapper.get('input[name="password_confirmation"]').setValue('New-password2!')
    await wrapper.get('form').trigger('submit')

    expect(resetPassword).toHaveBeenCalledWith({
      email: 'owner@example.test',
      token: 'reset-token',
      password: 'New-password2!',
      password_confirmation: 'New-password2!',
    })
    expect(push).toHaveBeenCalledWith({ name: 'login', query: { reset: 'success' } })
  })

  it('keeps the user on the form when the server rejects the token', async () => {
    query = { email: 'owner@example.test', token: 'expired-token' }
    const auth = useAuthStore()
    vi.spyOn(auth, 'resetPassword').mockImplementation(async () => {
      auth.passwordResetError =
        'This password reset link is invalid or has expired. Request a new link and try again.'
      return false
    })
    const wrapper = mount(ResetPasswordPage, {
      global: { stubs: { RouterLink: true } },
    })

    await wrapper.get('input[name="password"]').setValue('New-password2!')
    await wrapper.get('input[name="password_confirmation"]').setValue('New-password2!')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('[role="alert"]').text()).toContain('invalid or has expired')
    expect(push).not.toHaveBeenCalled()
  })

  it('shows a disabled loading action while reset is pending', async () => {
    query = { email: 'owner@example.test', token: 'reset-token' }
    const auth = useAuthStore()
    let finishReset: ((value: boolean) => void) | undefined
    vi.spyOn(auth, 'resetPassword').mockImplementation(
      () => new Promise<boolean>((resolve) => (finishReset = resolve)),
    )
    const wrapper = mount(ResetPasswordPage, {
      global: { stubs: { RouterLink: true } },
    })

    await wrapper.get('input[name="password"]').setValue('New-password2!')
    await wrapper.get('input[name="password_confirmation"]').setValue('New-password2!')
    await wrapper.get('form').trigger('submit')
    auth.passwordResetLoading = true
    await wrapper.vm.$nextTick()

    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button[type="submit"]').text()).toBe('Resetting…')

    finishReset?.(false)
    await flushPromises()
  })
})
