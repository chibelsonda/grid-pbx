import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/authStore'
import LoginPage from './LoginPage.vue'

const push = vi.fn<(location: unknown) => Promise<void>>().mockResolvedValue()
let query: Record<string, string> = {}

vi.mock('vue-router', () => ({
  useRoute: () => ({ query }),
  useRouter: () => ({ push }),
}))

describe('LoginPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    query = {}
    push.mockReset()
  })

  it('uses Zod feedback instead of submitting malformed credentials', async () => {
    const auth = useAuthStore()
    const login = vi.spyOn(auth, 'login')
    const wrapper = mount(LoginPage, {
      global: { stubs: { ToggleSwitch: true } },
    })

    await wrapper.get('input[name="email"]').setValue('invalid')
    await wrapper.get('input[name="password"]').setValue('')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('form').attributes('novalidate')).toBeDefined()
    expect(wrapper.text()).toContain('Enter a valid email address.')
    expect(wrapper.text()).toContain('Enter your password.')
    expect(login).not.toHaveBeenCalled()
    expect(push).not.toHaveBeenCalled()
  })

  it('offers password recovery and an accessible visibility toggle', async () => {
    const wrapper = mount(LoginPage, {
      global: {
        stubs: {
          ToggleSwitch: true,
          RouterLink: { template: '<a><slot /></a>' },
        },
      },
    })

    expect(wrapper.text()).toContain('Forgot password?')
    expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password')

    await wrapper.get('button[aria-label="Show password"]').trigger('click')

    expect(wrapper.get('input[name="password"]').attributes('type')).toBe('text')
  })

  it('does not follow an external redirect supplied in the query', async () => {
    query = { redirect: '//malicious.example.test' }
    const auth = useAuthStore()
    vi.spyOn(auth, 'login').mockResolvedValue()
    const wrapper = mount(LoginPage, {
      global: {
        stubs: {
          ToggleSwitch: true,
          RouterLink: true,
        },
      },
    })

    await wrapper.get('form').trigger('submit')

    expect(push).toHaveBeenCalledWith('/')
  })
})
