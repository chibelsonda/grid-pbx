import axios, { type AxiosRequestConfig } from 'axios'
import { normalizeApiErrorPayload } from './apiError'

declare module 'axios' {
  export interface AxiosRequestConfig {
    globalNotification?: boolean
  }

  export interface InternalAxiosRequestConfig {
    globalNotification?: boolean
  }
}

export const unexpectedServerErrorMessage =
  'An unexpected server error occurred. Try again. If the problem continues, contact support.'

type ApiErrorPayload = {
  message: string
  error_id?: string
}

export type HttpNotification = {
  title: string
  message: string
  tone: 'success' | 'error'
}

type HttpNotificationHandler = (notification: HttpNotification) => void
type MutationKind = 'upload' | 'delete' | 'update' | 'request'

let notificationHandler: HttpNotificationHandler | null = null

const mutationMessages: Record<
  MutationKind,
  { success: Omit<HttpNotification, 'tone'>; error: Omit<HttpNotification, 'tone'> }
> = {
  upload: {
    success: { title: 'Upload successful', message: 'The file was uploaded successfully.' },
    error: {
      title: 'Upload failed',
      message: 'The file could not be uploaded. Review the form or try again.',
    },
  },
  delete: {
    success: { title: 'Delete successful', message: 'The record was deleted successfully.' },
    error: { title: 'Delete failed', message: 'The record could not be deleted. Try again.' },
  },
  update: {
    success: { title: 'Update successful', message: 'The changes were saved successfully.' },
    error: {
      title: 'Update failed',
      message: 'The changes could not be saved. Review the form or try again.',
    },
  },
  request: {
    success: { title: 'Request successful', message: 'The request completed successfully.' },
    error: {
      title: 'Request failed',
      message: 'The request could not be completed. Review the form or try again.',
    },
  },
}

export function configureHttpNotifications(handler: HttpNotificationHandler | null): void {
  notificationHandler = handler
}

function isMultipartRequest(config: AxiosRequestConfig): boolean {
  if (typeof FormData !== 'undefined' && config.data instanceof FormData) return true

  return String(
    config.headers?.['Content-Type'] ?? config.headers?.['content-type'] ?? '',
  ).includes('multipart/form-data')
}

function mutationKind(config: AxiosRequestConfig): MutationKind | null {
  if (config.globalNotification === false) return null

  const method = config.method?.toLowerCase()
  if (!method || !['post', 'put', 'patch', 'delete'].includes(method)) return null
  if (isMultipartRequest(config)) return 'upload'
  if (method === 'delete') return 'delete'
  if (method === 'put' || method === 'patch') return 'update'

  return 'request'
}

export function mutationNotification(
  config: AxiosRequestConfig | undefined,
  successful: boolean,
  errorPayload?: unknown,
  errorStatus?: number,
): HttpNotification | null {
  if (!config) return null

  const kind = mutationKind(config)
  if (!kind) return null

  if (successful) return { ...mutationMessages[kind].success, tone: 'success' }

  const fallback = mutationMessages[kind].error
  const normalized = normalizeApiErrorPayload(errorPayload, fallback.message, errorStatus ?? null)

  // Field-level validation belongs to the persistent form summary and controls.
  // Emitting the same details globally creates two competing error messages.
  if (normalized.fieldErrorCount > 0) return null

  const supportReference = normalized.errorId ? ` Support reference: ${normalized.errorId}.` : ''

  return {
    title: fallback.title,
    message: `${normalized.message}${supportReference}`,
    tone: 'error',
  }
}

function notifyMutation(
  config: AxiosRequestConfig | undefined,
  successful: boolean,
  errorPayload?: unknown,
  errorStatus?: number,
): void {
  const notification = mutationNotification(config, successful, errorPayload, errorStatus)
  if (notification) notificationHandler?.(notification)
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

http.interceptors.response.use(
  (response) => {
    notifyMutation(response.config, true)

    return response
  },
  (error: unknown) => {
    if (axios.isAxiosError(error)) {
      if (error.response) {
        error.response.data = sanitizeApiErrorPayload(error.response.status, error.response.data)
      }

      if (error.code !== 'ERR_CANCELED') {
        notifyMutation(error.config, false, error.response?.data, error.response?.status)
      }
    }

    return Promise.reject(error)
  },
)

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
