import { http, type ApiResponse } from '@/shared/api/http'
import type { Account } from '../types/account'

export const accountApi = {
  async list(): Promise<Account[]> {
    const response = await http.get<ApiResponse<Account[]>>('/api/v1/accounts')

    return response.data.data
  },
}
