import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/authStore'
import LoginPage from './LoginPage.vue'

const push = vi.fn<(location: unknown) => Promise<void>>().mockResolvedValue()

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ push }),
}))

describe('LoginPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
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
})
