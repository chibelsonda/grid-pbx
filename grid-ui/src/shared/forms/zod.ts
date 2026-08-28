import type { ZodType } from 'zod'

export type FormErrors = Record<string, string[]>

export type FormValidationResult<T> =
  | { success: true; data: T; errors: FormErrors }
  | { success: false; data: null; errors: FormErrors }

export function validateForm<T>(schema: ZodType<T>, input: unknown): FormValidationResult<T> {
  const result = schema.safeParse(input)

  if (result.success) {
    return { success: true, data: result.data, errors: {} }
  }

  const errors: FormErrors = {}

  for (const issue of result.error.issues) {
    const field = issue.path.length > 0 ? issue.path.join('.') : '_form'
    ;(errors[field] ??= []).push(issue.message)
  }

  return { success: false, data: null, errors }
}
