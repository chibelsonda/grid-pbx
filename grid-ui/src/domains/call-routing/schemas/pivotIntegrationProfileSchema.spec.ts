import { describe, expect, it } from 'vitest'
import { pivotIntegrationProfileSchema } from './pivotIntegrationProfileSchema'

function validInput() {
  return {
    name: 'Customer IVR',
    is_active: true,
    voice_url: 'https://voice.example.test/pivot',
    cdr_url: '',
    methods: ['post'],
    formats: ['switch'],
    req_body_format: 'json',
    req_timeout_ms: 5000,
    headers: [{ name: 'X-Pivot-Key', value: 'private-secret' }],
  }
}

describe('pivotIntegrationProfileSchema', () => {
  it('accepts a bounded HTTPS profile with private X-headers', () => {
    expect(pivotIntegrationProfileSchema.safeParse(validInput()).success).toBe(true)
  })

  it('rejects non-HTTPS endpoints, duplicate headers, and empty capability choices', () => {
    const result = pivotIntegrationProfileSchema.safeParse({
      ...validInput(),
      voice_url: 'http://voice.example.test/pivot',
      methods: [],
      headers: [
        { name: 'X-Pivot-Key', value: 'one' },
        { name: 'x-pivot-key', value: 'two' },
      ],
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(
        expect.arrayContaining(['voice_url', 'methods', 'headers.1.name']),
      )
    }
  })

  it('rejects browser-controlled Pivot debug persistence', () => {
    expect(
      pivotIntegrationProfileSchema.safeParse({
        ...validInput(),
        debug: true,
      }).success,
    ).toBe(false)
  })
})
