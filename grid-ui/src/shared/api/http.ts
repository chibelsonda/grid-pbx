import axios from 'axios'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8081',
  headers: {
    Accept: 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

export type ApiResponse<T> = {
  data: T
}

/**
 * Axios names the HTTP body `data`, while the Laravel API uses a stable `data`
 * envelope. Keep that transport detail out of domain clients.
 */
export function unwrapApiData<T>(response: { data: ApiResponse<T> }): T {
  const { data: envelope } = response

  return envelope.data
}

export type PaginatedResponse<T> = {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}
