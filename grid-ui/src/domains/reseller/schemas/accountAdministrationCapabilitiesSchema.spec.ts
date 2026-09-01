import { describe, expect, it } from 'vitest'
import { accountAdministrationCapabilitiesSchema } from './accountAdministrationCapabilitiesSchema'

const capabilities = {
  account_creation_available: false,
  account_move_available: false,
  account_deletion_available: false,
  limit_mutations_available: false,
  service_plan_mutations_available: false,
  service_override_mutations_available: false,
  top_up_available: false,
  switch_service_synchronization_available: false,
  switch_service_reconciliation_available: false,
} as const

describe('accountAdministrationCapabilitiesSchema', () => {
  it('accepts only the fixed-false administration capability matrix', () => {
    expect(accountAdministrationCapabilitiesSchema.parse(capabilities)).toEqual(capabilities)
  })

  it('rejects enabled mutations and private operation data', () => {
    expect(() =>
      accountAdministrationCapabilitiesSchema.parse({
        ...capabilities,
        account_deletion_available: true,
      }),
    ).toThrow()
    expect(() =>
      accountAdministrationCapabilitiesSchema.parse({
        ...capabilities,
        accept_charges: true,
        service_plan_ids: ['raw-plan-id'],
      }),
    ).toThrow()
  })
})
