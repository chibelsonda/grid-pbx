import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { sessionApi } from '../api/sessionApi'
import type { PasswordResetMessage } from '../api/sessionApi'
import type { ForgotPasswordInput } from '../schemas/forgotPasswordFormSchema'
import type { LoginCredentials } from '../schemas/loginFormSchema'
import type { PasswordInput } from '../schemas/passwordFormSchema'
import type { ProfileInput } from '../schemas/profileFormSchema'
import type { ResetPasswordInput } from '../schemas/resetPasswordFormSchema'
import type { Session } from '../types/session'
import { useAuthStore } from './authStore'

vi.mock('../api/sessionApi', () => ({
  sessionApi: {
    current: vi.fn<() => Promise<Session>>(),
    login: vi.fn<(credentials: LoginCredentials) => Promise<Session>>(),
    logout: vi.fn<() => Promise<void>>(),
    requestPasswordReset: vi.fn<(input: ForgotPasswordInput) => Promise<PasswordResetMessage>>(),
    resetPassword: vi.fn<(input: ResetPasswordInput) => Promise<PasswordResetMessage>>(),
    updateProfile: vi.fn<(input: ProfileInput) => Promise<Session>>(),
    updatePassword: vi.fn<(input: PasswordInput) => Promise<void>>(),
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

  it('exposes password validation and clears the saving state', async () => {
    vi.mocked(sessionApi.updatePassword).mockRejectedValue({
      isAxiosError: true,
      response: {
        data: { errors: { current_password: ['The current password is incorrect.'] } },
      },
    })
    const auth = useAuthStore()

    const saved = await auth.updatePassword({
      current_password: 'wrong-password',
      password: 'new-secure-password',
      password_confirmation: 'new-secure-password',
    })

    expect(saved).toBe(false)
    expect(auth.passwordFieldErrors).toEqual({
      current_password: ['The current password is incorrect.'],
    })
    expect(auth.passwordError).toBeNull()
    expect(auth.passwordSaving).toBe(false)
  })
})

describe('useAuthStore password reset', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('exposes the generic forgot-password confirmation', async () => {
    vi.mocked(sessionApi.requestPasswordReset).mockResolvedValue({
      message: 'If an account exists, a link has been sent.',
    })
    const auth = useAuthStore()

    const successful = await auth.requestPasswordReset({ email: 'owner@example.test' })

    expect(successful).toBe(true)
    expect(sessionApi.requestPasswordReset).toHaveBeenCalledWith({ email: 'owner@example.test' })
    expect(auth.passwordResetMessage).toBe('If an account exists, a link has been sent.')
    expect(auth.passwordResetLoading).toBe(false)
  })

  it('exposes reset validation errors without a generic error', async () => {
    vi.mocked(sessionApi.resetPassword).mockRejectedValue({
      isAxiosError: true,
      response: { data: { errors: { password: ['Use a stronger password.'] } } },
    })
    const auth = useAuthStore()

    const successful = await auth.resetPassword({
      email: 'owner@example.test',
      token: 'reset-token',
      password: 'New-password2!',
      password_confirmation: 'New-password2!',
    })

    expect(successful).toBe(false)
    expect(auth.passwordResetFieldErrors).toEqual({ password: ['Use a stronger password.'] })
    expect(auth.passwordResetError).toBeNull()
    expect(auth.passwordResetLoading).toBe(false)
  })
})
