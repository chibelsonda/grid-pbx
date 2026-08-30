import axios from 'axios'

export const unexpectedServerErrorMessage =
  'An unexpected server error occurred. Try again. If the problem continues, contact support.'

type ApiErrorPayload = {
  message: string
  error_id?: string
}

export function sanitizeApiErrorPayload(status: number, payload: unknown): unknown {
  if (status < 500) return payload

  const errorId =
    typeof payload === 'object' &&
    payload !== null &&
    'error_id' in payload &&
    typeof payload.error_id === 'string'
      ? payload.error_id
      : undefined

  return {
    message: unexpectedServerErrorMessage,
    ...(errorId ? { error_id: errorId } : {}),
  } satisfies ApiErrorPayload
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8081',
  headers: {
    Accept: 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

http.interceptors.response.use(undefined, (error: unknown) => {
  if (axios.isAxiosError(error) && error.response) {
    error.response.data = sanitizeApiErrorPayload(error.response.status, error.response.data)
  }

  return Promise.reject(error)
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
