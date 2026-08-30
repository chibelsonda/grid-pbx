import { describe, expect, it } from 'vitest'
import { descendantOnboardingSchema } from './descendantOnboardingSchema'

describe('descendant onboarding schema', () => {
  it('requires a candidate, exact-name input, and access acknowledgement', () => {
    const result = descendantOnboardingSchema.safeParse({
      reference: '',
      confirmation: '',
      acknowledge_existing_access: false,
    })

    expect(result.success).toBe(false)
    if (result.success) return
    expect(result.error.flatten().fieldErrors.reference).toContain('Select a descendant account.')
    expect(result.error.flatten().fieldErrors.confirmation).toContain(
      'Enter the descendant account name.',
    )
    expect(result.error.flatten().fieldErrors.acknowledge_existing_access).toContain(
      'Acknowledge the inherited organization access.',
    )
  })
})
