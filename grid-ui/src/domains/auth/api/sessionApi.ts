import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { LoginCredentials } from '../schemas/loginFormSchema'
import type { ForgotPasswordInput } from '../schemas/forgotPasswordFormSchema'
import type { ProfileInput } from '../schemas/profileFormSchema'
import type { ResetPasswordInput } from '../schemas/resetPasswordFormSchema'
import type { Session } from '../types/session'

export type PasswordResetMessage = {
  message: string
}

export const sessionApi = {
  async current(): Promise<Session> {
    const response = await http.get<ApiResponse<Session>>('/api/v1/session')

    return unwrapApiData(response)
  },

  async login(credentials: LoginCredentials): Promise<Session> {
    await http.get('/sanctum/csrf-cookie')
    const response = await http.post<ApiResponse<Session>>('/login', credentials, {
      globalNotification: false,
    })

    return unwrapApiData(response)
  },

  async logout(): Promise<void> {
    await http.post('/logout', undefined, { globalNotification: false })
  },

  async requestPasswordReset(input: ForgotPasswordInput): Promise<PasswordResetMessage> {
    await http.get('/sanctum/csrf-cookie')

    return unwrapApiData(
      await http.post<ApiResponse<PasswordResetMessage>>('/forgot-password', input, {
        globalNotification: false,
      }),
    )
  },

  async resetPassword(input: ResetPasswordInput): Promise<PasswordResetMessage> {
    await http.get('/sanctum/csrf-cookie')

    return unwrapApiData(
      await http.post<ApiResponse<PasswordResetMessage>>('/reset-password', input, {
        globalNotification: false,
      }),
    )
  },

  async updateProfile(input: ProfileInput): Promise<Session> {
    return unwrapApiData(await http.patch<ApiResponse<Session>>('/api/v1/profile', input))
  },
}
