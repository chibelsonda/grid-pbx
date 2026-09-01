import { describe, expect, it } from 'vitest'
import { webhookIntegrationProfileSchema } from './webhookIntegrationProfileSchema'

describe('webhookIntegrationProfileSchema', () => {
  it('accepts a bounded HTTPS Webhook profile', () => {
    expect(
      webhookIntegrationProfileSchema.safeParse({
        name: 'CRM events',
        is_active: true,
        uri: 'https://events.example.test/calls',
        methods: ['post'],
        max_retries: 3,
      }).success,
    ).toBe(true)
  })

  it('rejects insecure URLs and retry values outside the Switch schema boundary', () => {
    expect(
      webhookIntegrationProfileSchema.safeParse({
        name: 'Unsafe',
        is_active: true,
        uri: 'http://events.example.test/calls',
        methods: ['post'],
        max_retries: 6,
      }).success,
    ).toBe(false)

    expect(
      webhookIntegrationProfileSchema.safeParse({
        name: 'Duplicate methods',
        is_active: true,
        uri: 'https://events.example.test/calls',
        methods: ['post', 'post'],
        max_retries: 2,
      }).success,
    ).toBe(false)
  })
})
