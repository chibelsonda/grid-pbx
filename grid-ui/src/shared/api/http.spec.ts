import { describe, expect, it } from 'vitest'
import { sanitizeApiErrorPayload, unexpectedServerErrorMessage, unwrapApiData } from './http'

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
