import { describe, expect, it } from 'vitest'
import { faxOperationCapabilitiesSchema } from './faxOperationCapabilitiesSchema'

const capability = {
  switch_supported: true,
  enabled: false,
  reason: 'Policy approval is required.',
} as const

describe('faxOperationCapabilitiesSchema', () => {
  it('accepts only the disabled public operation contract', () => {
    const result = faxOperationCapabilitiesSchema.safeParse({
      send: capability,
      forward: capability,
      resubmit: capability,
      delete_message: capability,
      delete_document: capability,
    })

    expect(result.success).toBe(true)
  })

  it('rejects raw Switch operation data', () => {
    const result = faxOperationCapabilitiesSchema.safeParse({
      send: { ...capability, url: 'http://switch/faxes/outgoing' },
      forward: capability,
      resubmit: capability,
      delete_message: capability,
      delete_document: capability,
    })

    expect(result.success).toBe(false)
  })
})
