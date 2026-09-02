import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { LoginCredentials } from '../schemas/loginFormSchema'
import type { PasswordInput } from '../schemas/passwordFormSchema'
import type { ProfileInput } from '../schemas/profileFormSchema'
import type { Session } from '../types/session'

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

  async updateProfile(input: ProfileInput): Promise<Session> {
    return unwrapApiData(await http.patch<ApiResponse<Session>>('/api/v1/profile', input))
  },

  async updatePassword(input: PasswordInput): Promise<void> {
    await http.patch('/api/v1/password', input, { globalNotification: false })
  },
}
