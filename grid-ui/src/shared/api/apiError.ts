import axios from 'axios'

export type ApiFieldErrors = Record<string, string[]>

export type NormalizedApiError = {
  message: string
  fieldErrors: ApiFieldErrors
  fieldErrorCount: number
  code: string | null
  errorId: string | null
  status: number | null
}

function record(value: unknown): Record<string, unknown> | null {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null
}

function text(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}

export function normalizeApiFieldErrors(value: unknown): ApiFieldErrors {
  const source = record(value)
  if (!source) return {}

  return Object.fromEntries(
    Object.entries(source).flatMap(([field, messages]) => {
      const normalized = (Array.isArray(messages) ? messages : [messages])
        .map(text)
        .filter((message): message is string => message !== null)

      return normalized.length > 0 ? [[field, normalized]] : []
    }),
  )
}

export function apiFieldErrorMessages(fieldErrors: ApiFieldErrors): string[] {
  return [...new Set(Object.values(fieldErrors).flat())]
}

export function apiFieldErrorCount(fieldErrors: ApiFieldErrors): number {
  return Object.values(fieldErrors).reduce((count, messages) => count + messages.length, 0)
}

export function apiValidationSummary(fieldErrors: ApiFieldErrors, fallback: string): string {
  const messages = apiFieldErrorMessages(fieldErrors)
  if (messages.length === 0) return fallback
  if (messages.length === 1) return messages[0]!

  return `${messages[0]} Review ${messages.length - 1} more ${messages.length === 2 ? 'issue' : 'issues'}.`
}

export function normalizeApiError(error: unknown, fallback: string): NormalizedApiError {
  const response = axios.isAxiosError(error) ? error.response : undefined
  const payload = record(response?.data)
  const fieldErrors = normalizeApiFieldErrors(payload?.errors)
  const fieldErrorCount = apiFieldErrorCount(fieldErrors)
  const serverMessage = text(payload?.message)
  const nativeMessage = error instanceof Error ? text(error.message) : null

  return {
    message:
      fieldErrorCount > 0
        ? apiValidationSummary(fieldErrors, serverMessage ?? fallback)
        : (serverMessage ?? nativeMessage ?? fallback),
    fieldErrors,
    fieldErrorCount,
    code: text(payload?.code),
    errorId: text(payload?.error_id),
    status: response?.status ?? null,
  }
}

export function normalizeApiErrorPayload(
  payload: unknown,
  fallback: string,
  status: number | null = null,
): NormalizedApiError {
  const body = record(payload)
  const fieldErrors = normalizeApiFieldErrors(body?.errors)
  const fieldErrorCount = apiFieldErrorCount(fieldErrors)
  const serverMessage = text(body?.message)

  return {
    message:
      fieldErrorCount > 0
        ? apiValidationSummary(fieldErrors, serverMessage ?? fallback)
        : (serverMessage ?? fallback),
    fieldErrors,
    fieldErrorCount,
    code: text(body?.code),
    errorId: text(body?.error_id),
    status,
  }
}
