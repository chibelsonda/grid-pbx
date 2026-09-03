import axios from 'axios'
import { defineStore } from 'pinia'
import { sessionApi } from '../api/sessionApi'
import type { LoginCredentials } from '../schemas/loginFormSchema'
import type { ForgotPasswordInput } from '../schemas/forgotPasswordFormSchema'
import type { ProfileInput } from '../schemas/profileFormSchema'
import type { ResetPasswordInput } from '../schemas/resetPasswordFormSchema'
import type { SessionUser } from '../types/session'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as SessionUser | null,
    initialized: false,
    loading: false,
    error: null as string | null,
    passwordResetLoading: false,
    passwordResetMessage: null as string | null,
    passwordResetError: null as string | null,
    passwordResetFieldErrors: {} as Record<string, string[]>,
    profileSaving: false,
    profileError: null as string | null,
    profileFieldErrors: {} as Record<string, string[]>,
  }),
  getters: {
    authenticated: (state) => state.user !== null,
  },
  actions: {
    async restore(): Promise<void> {
      if (this.initialized) return

      try {
        this.user = (await sessionApi.current()).user
      } catch (error) {
        if (!axios.isAxiosError(error) || error.response?.status !== 401) throw error
        this.user = null
      } finally {
        this.initialized = true
      }
    },
    async login(credentials: LoginCredentials): Promise<void> {
      this.loading = true
      this.error = null

      try {
        this.user = (await sessionApi.login(credentials)).user
        this.initialized = true
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'The provided credentials are incorrect.')
          : 'Unable to sign in right now.'
        throw error
      } finally {
        this.loading = false
      }
    },
    async logout(): Promise<void> {
      try {
        await sessionApi.logout()
      } finally {
        this.user = null
        this.initialized = true
      }
    },
    clearPasswordResetState(): void {
      this.passwordResetMessage = null
      this.passwordResetError = null
      this.passwordResetFieldErrors = {}
    },
    async requestPasswordReset(input: ForgotPasswordInput): Promise<boolean> {
      this.passwordResetLoading = true
      this.clearPasswordResetState()

      try {
        this.passwordResetMessage = (await sessionApi.requestPasswordReset(input)).message
        return true
      } catch (error) {
        this.passwordResetFieldErrors = axios.isAxiosError(error)
          ? (error.response?.data?.errors ?? {})
          : {}
        this.passwordResetError =
          Object.keys(this.passwordResetFieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to request a reset link right now.')
              : 'Unable to request a reset link right now.'
        return false
      } finally {
        this.passwordResetLoading = false
      }
    },
    async resetPassword(input: ResetPasswordInput): Promise<boolean> {
      this.passwordResetLoading = true
      this.clearPasswordResetState()

      try {
        this.passwordResetMessage = (await sessionApi.resetPassword(input)).message
        return true
      } catch (error) {
        this.passwordResetFieldErrors = axios.isAxiosError(error)
          ? (error.response?.data?.errors ?? {})
          : {}
        this.passwordResetError =
          Object.keys(this.passwordResetFieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to reset your password right now.')
              : 'Unable to reset your password right now.'
        return false
      } finally {
        this.passwordResetLoading = false
      }
    },
    clearProfileError(): void {
      this.profileError = null
      this.profileFieldErrors = {}
    },
    async updateProfile(input: ProfileInput): Promise<boolean> {
      this.profileSaving = true
      this.clearProfileError()

      try {
        this.user = (await sessionApi.updateProfile(input)).user
        return true
      } catch (error) {
        this.profileFieldErrors = axios.isAxiosError(error)
          ? (error.response?.data?.errors ?? {})
          : {}
        this.profileError =
          Object.keys(this.profileFieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to update your profile.')
              : 'Unable to update your profile.'
        return false
      } finally {
        this.profileSaving = false
      }
    },
  },
})
