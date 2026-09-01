import { describe, expect, it } from 'vitest'
import { createSandboxRefundFormSchema } from './paymentSchema'

describe('createSandboxRefundFormSchema', () => {
  it('accepts only whole positive cents within the current account safety limit', () => {
    const schema = createSandboxRefundFormSchema(75)

    expect(schema.parse({ amount_minor: 75 })).toEqual({ amount_minor: 75 })
    expect(schema.safeParse({ amount_minor: 0 }).success).toBe(false)
    expect(schema.safeParse({ amount_minor: 1.5 }).success).toBe(false)
    expect(schema.safeParse({ amount_minor: 76 }).success).toBe(false)
  })
})
