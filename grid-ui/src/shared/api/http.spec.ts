import { describe, expect, it } from 'vitest'
import {
  mutationNotification,
  sanitizeApiErrorPayload,
  unexpectedServerErrorMessage,
  unwrapApiData,
} from './http'

describe('unwrapApiData', () => {
  it('returns the Laravel data envelope from an Axios-shaped response', () => {
    expect(unwrapApiData({ data: { data: { id: 'public-id' } } })).toEqual({
      id: 'public-id',
    })
  })
})

describe('sanitizeApiErrorPayload', () => {
  it('removes backend details from unexpected server errors and retains a safe error reference', () => {
    expect(
      sanitizeApiErrorPayload(500, {
        message: 'SQLSTATE[23000]: update switch_accounts set secret = value',
        exception: 'Illuminate\\Database\\QueryException',
        trace: ['sensitive stack frame'],
        error_id: '7e479539-9268-48ce-88c8-4ac95512614c',
      }),
    ).toEqual({
      message: unexpectedServerErrorMessage,
      error_id: '7e479539-9268-48ce-88c8-4ac95512614c',
    })
  })

  it('preserves intentional client errors such as validation responses', () => {
    const validation = {
      message: 'The given data was invalid.',
      errors: { name: ['Enter a name.'] },
    }

    expect(sanitizeApiErrorPayload(422, validation)).toBe(validation)
  })
})

describe('mutationNotification', () => {
  it('classifies successful record updates without exposing request details', () => {
    expect(
      mutationNotification(
        { method: 'patch', url: '/api/v1/accounts/private-id/records/raw-id' },
        true,
      ),
    ).toEqual({
      title: 'Update successful',
      message: 'The changes were saved successfully.',
      tone: 'success',
    })
  })

  it('classifies failed uploads and deletes with actionable generic messages', () => {
    expect(mutationNotification({ method: 'post', data: new FormData() }, false)).toEqual({
      title: 'Upload failed',
      message: 'The file could not be uploaded. Review the form or try again.',
      tone: 'error',
    })
    expect(mutationNotification({ method: 'delete' }, false)).toEqual({
      title: 'Delete failed',
      message: 'The record could not be deleted. Try again.',
      tone: 'error',
    })
  })

  it('ignores reads and explicitly silent session mutations', () => {
    expect(mutationNotification({ method: 'get' }, true)).toBeNull()
    expect(mutationNotification({ method: 'post', globalNotification: false }, false)).toBeNull()
  })
})
