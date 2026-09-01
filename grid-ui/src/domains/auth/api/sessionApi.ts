import { http, unwrapApiData, type ApiResponse } from '@/shared/api/http'
import type { ProfileInput } from '../schemas/profileFormSchema'
import type { LoginCredentials, Session } from '../types/session'

export const sessionApi = {
  async current(): Promise<Session> {
    const response = await http.get<ApiResponse<Session>>('/api/v1/session')

    return unwrapApiData(response)
  },

  async login(credentials: LoginCredentials): Promise<Session> {
    await http.get('/sanctum/csrf-cookie')
    const response = await http.post<ApiResponse<Session>>('/login', credentials)

    return unwrapApiData(response)
  },

  async logout(): Promise<void> {
    await http.post('/logout')
  },

  async updateProfile(input: ProfileInput): Promise<Session> {
    return unwrapApiData(await http.patch<ApiResponse<Session>>('/api/v1/profile', input))
  },
}
