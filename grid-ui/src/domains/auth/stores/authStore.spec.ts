import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { sessionApi } from '../api/sessionApi'
import { useAuthStore } from './authStore'

vi.mock('../api/sessionApi', () => ({
  sessionApi: {
    current: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
    updateProfile: vi.fn(),
  },
}))

describe('useAuthStore profile updates', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('replaces the session user with the public profile response', async () => {
    vi.mocked(sessionApi.updateProfile).mockResolvedValue({
      user: {
        id: 'a346c2a8-89d5-4f55-a0bd-43c366746115',
        name: 'Operations Admin',
        email: 'admin@example.test',
      },
    })
    const auth = useAuthStore()

    const saved = await auth.updateProfile({ name: 'Operations Admin' })

    expect(saved).toBe(true)
    expect(auth.user?.id).toBe('a346c2a8-89d5-4f55-a0bd-43c366746115')
    expect(auth.user?.name).toBe('Operations Admin')
    expect(auth.profileSaving).toBe(false)
  })

  it('exposes server validation without replacing the current session user', async () => {
    vi.mocked(sessionApi.updateProfile).mockRejectedValue({
      isAxiosError: true,
      response: { data: { errors: { name: ['Enter your display name.'] } } },
    })
    const auth = useAuthStore()
    auth.user = {
      id: 'a346c2a8-89d5-4f55-a0bd-43c366746115',
      name: 'Grid Admin',
      email: 'admin@example.test',
    }

    const saved = await auth.updateProfile({ name: '' })

    expect(saved).toBe(false)
    expect(auth.user.name).toBe('Grid Admin')
    expect(auth.profileFieldErrors).toEqual({ name: ['Enter your display name.'] })
    expect(auth.profileError).toBeNull()
  })
})
