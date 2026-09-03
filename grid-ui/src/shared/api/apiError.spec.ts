import { describe, expect, it } from 'vitest'
import {
  apiFieldErrorCount,
  apiValidationSummary,
  normalizeApiError,
  normalizeApiFieldErrors,
} from './apiError'

describe('API error normalization', () => {
  it('normalizes Laravel field errors and ignores unsafe shapes', () => {
    expect(
      normalizeApiFieldErrors({
        name: ['Enter a name.'],
        extension: 'Enter an extension.',
        ignored: { internal: true },
      }),
    ).toEqual({
      name: ['Enter a name.'],
      extension: ['Enter an extension.'],
    })
  })

  it('builds a concise validation summary without hiding additional issues', () => {
    const errors = {
      temporal_rule_ids: ['Select at least one temporal rule.'],
      temporal_rule_routes: ['Configure at least one temporal route.'],
      menu_branches: ['Configure the menu branches.'],
    }

    expect(apiFieldErrorCount(errors)).toBe(3)
    expect(apiValidationSummary(errors, 'Review the form.')).toBe(
      'Select at least one temporal rule. Review 2 more issues.',
    )
  })

  it('extracts status, code, support reference, and field errors from Axios errors', () => {
    const error = {
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          message: 'The submitted configuration is invalid.',
          code: 'validation_failed',
          error_id: 'safe-reference',
          errors: { name: ['Enter a name.'] },
        },
      },
      toJSON: () => ({}),
    }

    expect(normalizeApiError(error, 'Unable to save.')).toEqual({
      message: 'Enter a name.',
      fieldErrors: { name: ['Enter a name.'] },
      fieldErrorCount: 1,
      code: 'validation_failed',
      errorId: 'safe-reference',
      status: 422,
    })
  })

  it('preserves a useful local error when no HTTP response exists', () => {
    expect(normalizeApiError(new Error('Synchronization timed out.'), 'Unable to sync.').message).toBe(
      'Synchronization timed out.',
    )
  })
})
