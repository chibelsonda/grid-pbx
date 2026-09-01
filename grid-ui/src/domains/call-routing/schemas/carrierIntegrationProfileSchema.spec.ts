import { describe, expect, it } from 'vitest'
import { carrierIntegrationProfileSchema } from './carrierIntegrationProfileSchema'

describe('carrierIntegrationProfileSchema', () => {
  it('accepts only the fixed global scope for Global Carrier', () => {
    expect(
      carrierIntegrationProfileSchema.safeParse({
        integration_type: 'global_carrier',
        name: 'System carriers',
        is_active: true,
        route_scope: 'global',
      }).success,
    ).toBe(true)
    expect(
      carrierIntegrationProfileSchema.safeParse({
        integration_type: 'global_carrier',
        name: 'Unsafe override',
        is_active: true,
        route_scope: 'reseller',
      }).success,
    ).toBe(false)
  })

  it('allows only account or reseller scope for Account Carrier', () => {
    for (const route_scope of ['account', 'reseller']) {
      expect(
        carrierIntegrationProfileSchema.safeParse({
          integration_type: 'account_carrier',
          name: 'Owned resources',
          is_active: true,
          route_scope,
        }).success,
      ).toBe(true)
    }
  })
})
