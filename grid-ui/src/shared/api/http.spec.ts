import { describe, expect, it } from 'vitest'
import { unwrapApiData } from './http'

describe('unwrapApiData', () => {
  it('returns the Laravel data envelope from an Axios-shaped response', () => {
    expect(unwrapApiData({ data: { data: { id: 'public-id' } } })).toEqual({
      id: 'public-id',
    })
  })
})
