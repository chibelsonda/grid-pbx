import { http, type ApiResponse } from '@/shared/api/http'
import type { LoginCredentials, Session } from '../types/session'

export const sessionApi = {
  async current(): Promise<Session> {
    const response = await http.get<ApiResponse<Session>>('/api/v1/session')

    return response.data.data
  },

  async login(credentials: LoginCredentials): Promise<Session> {
    await http.get('/sanctum/csrf-cookie')
    const response = await http.post<ApiResponse<Session>>('/login', credentials)

    return response.data.data
  },

  async logout(): Promise<void> {
    await http.post('/logout')
  },
}
